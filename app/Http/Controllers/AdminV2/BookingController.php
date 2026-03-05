<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Service;
use App\Services\ServiceFeeService;
use App\Services\WalletFeeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    private const DEPOSIT_MAX_PERCENT = 20; // max deposit = 20% of booking price
    private const EXECUTION_FEE_CODE = 'booking_execution_fee';

    // =========================
    // INDEX
    // =========================
    public function index(Request $request)
    {
        $q = Booking::query();

        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', (int)$request->user_id);
        }
        if ($request->filled('business_id')) {
            $q->where('business_id', (int)$request->business_id);
        }
        if ($request->filled('service_id')) {
            $q->where('service_id', (int)$request->service_id);
        }

        $perPage = (int)($request->get('perPage', 50));
        $rows = $q->orderByDesc('id')->paginate($perPage);

        return view('admin-v2.bookings.index', [
            'rows' => $rows,
            'filters' => [
                'status' => $request->get('status'),
                'user_id' => $request->get('user_id'),
                'business_id' => $request->get('business_id'),
                'service_id' => $request->get('service_id'),
                'perPage' => $perPage,
            ],
            'statusOptions' => Booking::statusOptions(),
        ]);
    }

    // =========================
    // CREATE / STORE
    // =========================
    public function create()
    {
        return view('admin-v2.bookings.create', [
            'statusOptions' => Booking::statusOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateBooking($request, false);

        // auto price from service * qty
        $data['price'] = $this->autoPriceFromService(
            (int)$data['service_id'],
            (int)($data['quantity'] ?? 1)
        );

        $booking = Booking::create($data);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم إنشاء الحجز بنجاح.');
    }

    // =========================
    // SHOW
    // =========================
    public function show(Booking $booking)
    {
        $booking = Booking::withTrashed()->findOrFail($booking->id);

        $booking->load([
            'user:id,name,code,type',
            'business:id,name,code,type,booking_hold_enabled,booking_hold_amount',
            'service:id,business_id,name_ar,name_en,price,duration',
        ]);

        $deposit = $this->latestDeposit($booking);

        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

        $depositPolicy = $this->depositPolicy($booking); // required/hold/max/percent

        return view('admin-v2.bookings.show', compact(
            'booking',
            'deposit',
            'clientConfirmed',
            'businessConfirmed',
            'depositPolicy'
        ));
    }

    // =========================
    // EDIT / UPDATE
    // =========================
    public function edit(Booking $booking)
    {
        return view('admin-v2.bookings.edit', [
            'booking' => $booking,
            'statusOptions' => Booking::statusOptions(),
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $oldStatus = (string)$booking->status;

        $data = $this->validateBooking($request, true);

        // auto price
        if (array_key_exists('service_id', $data) || array_key_exists('quantity', $data)) {
            $serviceId = (int)($data['service_id'] ?? $booking->service_id);
            $qty = (int)($data['quantity'] ?? ($booking->quantity ?? 1));
            $data['price'] = $this->autoPriceFromService($serviceId, $qty);
        }

        DB::transaction(function () use ($booking, $data, $oldStatus) {

            // لا تكتب null إلى date/time إذا لم تُرسل (مهم لحل خطأ date cannot be null)
            $booking->fill($data);
            $booking->save();

            $newStatus = (string)$booking->status;

            // عند بداية التنفيذ: in_progress
            if ($oldStatus !== Booking::STATUS_IN_PROGRESS && $newStatus === Booking::STATUS_IN_PROGRESS) {

                // deposit policy
                $deposit = Deposit::query()
                    ->where('target_type', Booking::class)
                    ->where('target_id', (int)$booking->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $depositPolicy = $this->depositPolicy($booking);

                // ✅ Deposit مطلوب فقط لو البزنس مفعل التوجل
                if ($depositPolicy['required'] && !$deposit) {
                    abort(422, 'Deposit مطلوب لهذا البزنس قبل بدء التنفيذ.');
                }

                // ✅ تأكيد الطرفين شرط أساسي دائمًا (deposit أو meta)
                [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

                if (!$clientConfirmed || !$businessConfirmed) {
                    abort(422, 'يجب تأكيد الطرفين قبل بدء التنفيذ.');
                }

                // لو deposit موجود: تأكد frozen
                if ($deposit && $deposit->status !== 'frozen') {
                    $deposit->status = 'frozen';
                    $deposit->save();
                }

                // ✅ خصم رسوم التنفيذ Split مرة واحدة
                $this->chargeExecutionFeeSplitOnce($booking);
            }
        });

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم تحديث الحجز بنجاح.');
    }

    // =========================
    // DESTROY
    // =========================
    public function destroy(Booking $booking)
    {
        $booking->delete();
        return redirect()->route('admin.bookings.index')->with('success', 'تم حذف الحجز.');
    }

    // =========================
    // AJAX service lookup
    // =========================
    public function serviceLookup(Request $request)
    {
        $term = trim((string)$request->get('q', ''));

        $services = Service::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where('name_ar', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id','business_id','name_ar','name_en','price','duration']);

        return response()->json(['ok' => true, 'services' => $services]);
    }

    // =========================
    // Confirmations (required always)
    // =========================
    public function startConfirmClient(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->client_confirmed = 1;
                $deposit->save();
                return;
            }

            // بدون deposit: نخزن على meta
            $meta = $booking->meta ?? [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['client'] = 1;
            $meta['_start_confirm']['client_at'] = now()->toDateTimeString();
            $booking->meta = $meta;
            $booking->save();
        });

        return back()->with('success', 'تم تأكيد العميل.');
    }

    public function startConfirmBusiness(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->business_confirmed = 1;
                $deposit->save();
                return;
            }

            // بدون deposit: نخزن على meta
            $meta = $booking->meta ?? [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['business'] = 1;
            $meta['_start_confirm']['business_at'] = now()->toDateTimeString();
            $booking->meta = $meta;
            $booking->save();
        });

        return back()->with('success', 'تم تأكيد البزنس.');
    }

    public function depositConfirmClient(Booking $booking)
    {
        // في مشروعك موجود route باسم deposit.confirmClient
        return $this->startConfirmClient($booking);
    }

    // =========================
    // Deposit actions
    // =========================
    public function depositFreeze(Booking $booking)
    {
        $exists = Deposit::where('target_type', Booking::class)
            ->where('target_id', $booking->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Deposit موجود بالفعل.');
        }

        $booking->loadMissing('business:id,booking_hold_enabled,booking_hold_amount');

        if (!$booking->user_id || !$booking->business_id) {
            return back()->with('error', 'الحجز غير مكتمل (عميل أو بزنس مفقود).');
        }

        $enabled = (bool)($booking->business->booking_hold_enabled ?? false);
        $hold    = (float)($booking->business->booking_hold_amount ?? 0);

        if (!$enabled || $hold <= 0) {
            return back()->with('error', 'Deposit غير مفعل لهذا البزنس.');
        }

        // ✅ حد 20% من السعر
        $maxHold = $this->depositMaxAllowedAmount($booking);
        if ($maxHold <= 0) {
            return back()->with('error', 'لا يمكن إنشاء Deposit لأن سعر الحجز غير متاح.');
        }
        if ($hold > $maxHold) {
            return back()->with(
                'error',
                'قيمة الـ Deposit المحددة من البزنس أكبر من الحد المسموح (' . self::DEPOSIT_MAX_PERCENT . '%). '
                . 'الحد الأقصى: ' . number_format($maxHold, 2)
                . ' / المحدد: ' . number_format($hold, 2)
            );
        }

        $total          = round($hold * 2, 2);
        $clientAmount   = round($hold, 2);
        $businessAmount = round($hold, 2);

        DB::transaction(function () use ($booking, $total, $clientAmount, $businessAmount) {
            Deposit::create([
                'client_id'          => (int)$booking->user_id,
                'business_id'        => (int)$booking->business_id,
                'target_type'        => Booking::class,
                'target_id'          => (int)$booking->id,
                'total_amount'       => $total,
                'client_percent'     => 50,
                'business_percent'   => 50,
                'client_amount'      => $clientAmount,
                'business_amount'    => $businessAmount,
                'status'             => 'frozen',
                'client_confirmed'   => 0,
                'business_confirmed' => 0,
            ]);
        });

        return back()->with('success', 'تم إنشاء Deposit بنجاح.');
    }

    public function depositRelease(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) return back()->with('error', 'لا يوجد Deposit.');

        $deposit->status = 'released';
        $deposit->released_at = now();
        $deposit->save();

        return back()->with('success', 'تم Release للـ Deposit.');
    }

    public function depositRefund(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) return back()->with('error', 'لا يوجد Deposit.');

        $deposit->status = 'refunded';
        $deposit->refunded_at = now();
        $deposit->save();

        return back()->with('success', 'تم Refund للـ Deposit.');
    }

    public function depositDisputeOpen(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) return back()->with('error', 'لا يوجد Deposit.');

        $deposit->status = 'dispute';
        $deposit->dispute_opened_at = now();
        $deposit->dispute_opened_by = 'admin';
        $deposit->save();

        return back()->with('success', 'تم فتح النزاع.');
    }

    public function depositAgreeRelease(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) return back()->with('error', 'لا يوجد Deposit.');

        $deposit->release_agreed_client = 1;
        $deposit->release_agreed_business = 1;
        $deposit->save();

        return back()->with('success', 'تمت الموافقة على Release.');
    }

    public function depositAgreeRefund(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) return back()->with('error', 'لا يوجد Deposit.');

        $deposit->refund_agreed_client = 1;
        $deposit->refund_agreed_business = 1;
        $deposit->save();

        return back()->with('success', 'تمت الموافقة على Refund.');
    }

    // =========================
    // VALIDATION
    // =========================
    private function validateBooking(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'user_id'     => ['required','integer'],
            'business_id' => ['required','integer'],
            'service_id'  => ['required','integer'],

            'starts_at'   => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date'],
            'ends_at'     => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date'],

            'duration_value' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'duration_unit'  => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', Rule::in(['minute','hour','day','week','month','year'])],

            'all_day'   => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'boolean'],
            'timezone'  => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:64'],

            'quantity'  => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'party_size'=> [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],

            'bookable_type' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:120'],
            'bookable_id'   => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer'],

            'status' => ['required', Rule::in(array_keys(Booking::statusOptions()))],
            'notes'  => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string'],
        ];

        // DB: date/time NOT NULL → create required / update sometimes
        if (!$isUpdate) {
            $rules['date'] = ['required','date'];
            $rules['time'] = ['required'];
        } else {
            $rules['date'] = ['sometimes','nullable','date'];
            $rules['time'] = ['sometimes','nullable'];
        }

        $data = $request->validate($rules);

        // fallback: derive date/time from starts_at
        if (
            (empty($data['date']) || empty($data['time']))
            && !empty($data['starts_at'])
        ) {
            $dt = Carbon::parse($data['starts_at']);
            $data['date'] = $data['date'] ?? $dt->toDateString();
            $data['time'] = $data['time'] ?? $dt->format('H:i:s');
        }

        // update: if date/time explicitly null, don't write null
        if ($isUpdate) {
            if (array_key_exists('date', $data) && $data['date'] === null) unset($data['date']);
            if (array_key_exists('time', $data) && $data['time'] === null) unset($data['time']);
        }

        return $data;
    }

    private function autoPriceFromService(int $serviceId, int $qty = 1): float
    {
        $qty = max(1, $qty);
        $service = Service::query()->find($serviceId);
        $base = $service ? (float)($service->price ?? 0) : 0.0;
        return round($base * $qty, 2);
    }

    private function latestDeposit(Booking $booking): ?Deposit
    {
        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int)$booking->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Confirmations always required:
     * - If deposit exists: use deposit flags.
     * - Else: use booking.meta['_start_confirm'].
     */
    private function resolveConfirmState(Booking $booking, ?Deposit $deposit): array
    {
        if ($deposit) {
            $client = ((int)$deposit->client_confirmed === 1);
            $business = ((int)$deposit->business_confirmed === 1);
            return [$client, $business];
        }

        $meta = $booking->meta ?? [];
        $sc = $meta['_start_confirm'] ?? [];
        $client = !empty($sc['client']);
        $business = !empty($sc['business']);
        return [$client, $business];
    }

    private function depositMaxAllowedAmount(Booking $booking): float
    {
        $price = (float)($booking->price ?? 0);
        if ($price <= 0) return 0.0;
        return round($price * (self::DEPOSIT_MAX_PERCENT / 100), 2);
    }

    /**
     * Deposit required by business toggle:
     * booking_hold_enabled + booking_hold_amount > 0
     */
    private function depositPolicy(Booking $booking): array
    {
        $booking->loadMissing('business:id,booking_hold_enabled,booking_hold_amount');

        $enabled = (bool)($booking->business->booking_hold_enabled ?? false);
        $hold = (float)($booking->business->booking_hold_amount ?? 0);

        return [
            'required' => $enabled && $hold > 0,
            'hold'     => round($hold, 2),
            'max'      => $this->depositMaxAllowedAmount($booking),
            'percent'  => self::DEPOSIT_MAX_PERCENT,
        ];
    }

    /**
     * Charge split execution fee once:
     * code = booking_execution_fee
     * rules = {"client_amount":1,"business_amount":1}
     */
    private function chargeExecutionFeeSplitOnce(Booking $booking): void
    {
        $booking->refresh();

        $meta = $booking->meta ?? [];
        if (!empty($meta['_execution_fee']['charged_at'])) {
            return; // already charged
        }

        /** @var ServiceFeeService $feeService */
        $feeService = app(ServiceFeeService::class);

        $fee = $feeService->getByCodeForService(self::EXECUTION_FEE_CODE, (int)$booking->service_id);
        if (!$fee) {
            return; // fee not configured
        }

        // split amounts from rules
        [$clientFee, $businessFee] = $feeService->resolveSplit($fee, (float)($booking->price ?? 0));

        $clientFee = round((float)$clientFee, 2);
        $businessFee = round((float)$businessFee, 2);

        if (($clientFee + $businessFee) <= 0) {
            return;
        }

        /** @var WalletFeeService $walletFee */
        $walletFee = app(WalletFeeService::class);

        $walletFee->chargeSplitToApp(
            (int)$booking->user_id,
            (int)$booking->business_id,
            $clientFee,
            $businessFee,
            Booking::class,
            (string)$booking->id,
            'Booking execution fee'
        );

        $meta['_execution_fee'] = [
            'code'            => self::EXECUTION_FEE_CODE,
            'client_amount'   => $clientFee,
            'business_amount' => $businessFee,
            'charged_at'      => now()->toDateTimeString(),
        ];

        $booking->meta = $meta;
        $booking->save();
    }

    private function autoPriceFromServiceForBusiness(int $serviceId, int $businessId, int $qty = 1): float
    {
        $qty = max(1, $qty);

        $base = (float) Service::query()->whereKey($serviceId)->value('price');

        $businessPrice = \App\Models\BusinessServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->value('price');

        if ($businessPrice !== null) {
            $base = (float) $businessPrice;
        }

        return round($base * $qty, 2);
    }

}