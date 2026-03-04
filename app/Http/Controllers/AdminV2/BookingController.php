<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceFeeService;
use App\Services\WalletFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
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
        $data = $this->validateBooking($request);

        // Auto price (لو عندك logic تانية، عدّل هنا)
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
        // SoftDeletes مدعوم عندك (deleted_at موجود)
        $booking = Booking::withTrashed()->findOrFail($booking->id);

        $booking->load([
            'user:id,name,code,type',
            'business:id,name,code,type,booking_hold_enabled,booking_hold_amount',
            'service:id,business_id,name_ar,name_en,price,duration',
        ]);

        $deposit = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();

        // confirmations قد تكون من deposit أو من meta (لو deposit غير مطلوب/غير موجود)
        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

        return view('admin-v2.bookings.show', compact('booking', 'deposit', 'clientConfirmed', 'businessConfirmed'));
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

        $data = $this->validateBooking($request, $booking->id);

        // price computed
        $data['price'] = $this->autoPriceFromService(
            (int)$data['service_id'],
            (int)($data['quantity'] ?? 1)
        );

        DB::transaction(function () use ($booking, $data, $oldStatus) {

            $booking->fill($data);
            $booking->save();

            $newStatus = (string)$booking->status;

            // عند بداية التنفيذ: accepted -> in_progress (أو أي انتقال)
            if ($oldStatus !== Booking::STATUS_IN_PROGRESS && $newStatus === Booking::STATUS_IN_PROGRESS) {

                // اجلب deposit إن وجد (قد لا يكون موجود)
                $deposit = Deposit::query()
                    ->where('target_type', Booking::class)
                    ->where('target_id', (int) $booking->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                // business settings: هل deposit مطلوب؟
                $booking->loadMissing('business:id,booking_hold_enabled,booking_hold_amount');

                $depositRequired = (bool)($booking->business->booking_hold_enabled ?? false)
                    && (float)($booking->business->booking_hold_amount ?? 0) > 0;

                if ($depositRequired && !$deposit) {
                    // ممنوع يبدأ تنفيذ بدون deposit إذا صاحب الخدمة اشترطه
                    abort(422, 'Deposit is required before starting execution for this business.');
                }

                // confirmations: شرط أساسي دائمًا
                [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

                if (!$clientConfirmed || !$businessConfirmed) {
                    abort(422, 'يجب تأكيد الطرفين قبل بدء التنفيذ.');
                }

                // لو deposit موجود: تأكد frozen
                if ($deposit && $deposit->status !== 'frozen') {
                    $deposit->status = 'frozen';
                    $deposit->save();
                }

                // خصم رسوم التنفيذ split مرة واحدة (idempotent عبر meta)
                $this->chargeExecutionFeeSplitOnce($booking);
            }

            // عند اكتمال الحجز (completed) - هنا لا نخصم رسوم جديدة
            // ويمكنك لاحقًا ربط release deposit هنا إذا أردت (لكن حسب نظامك)
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
    // SERVICE LOOKUP (AJAX)
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

        return response()->json([
            'ok' => true,
            'services' => $services,
        ]);
    }

    // =========================
    // CONFIRMATIONS (Client/Business)
    // - شرط أساسي دائمًا حتى لو deposit غير موجود
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

            // بدون deposit: سجل في meta
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

            // بدون deposit: سجل في meta
            $meta = $booking->meta ?? [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['business'] = 1;
            $meta['_start_confirm']['business_at'] = now()->toDateTimeString();
            $booking->meta = $meta;
            $booking->save();
        });

        return back()->with('success', 'تم تأكيد البزنس.');
    }

    // موجود في routes عندك
    public function depositConfirmClient(Booking $booking)
    {
        // لو عندك منطق مختلف، عدّله.
        // حالياً: اعتباره مثل client_confirmed
        return $this->startConfirmClient($booking);
    }

    // =========================
    // DEPOSIT ACTIONS
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

        $hold    = (float) ($booking->business->booking_hold_amount ?? 0);
        $enabled = (bool)  ($booking->business->booking_hold_enabled ?? false);

        if (!$enabled || $hold <= 0) {
            return back()->with('error', 'Deposit غير مفعل لهذا البزنس.');
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
    // Helpers
    // =========================

    private function validateBooking(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'user_id'      => ['required','integer'],
            'business_id'  => ['required','integer'],
            'service_id'   => ['required','integer'],

            'date'         => ['nullable','date'],
            'time'         => ['nullable'],

            'starts_at'    => ['nullable','date'],
            'ends_at'      => ['nullable','date'],

            'duration_value'=> ['nullable','integer','min:1'],
            'duration_unit' => ['nullable', Rule::in(['minute','hour','day','week','month','year'])],

            'all_day'      => ['nullable','boolean'],
            'timezone'     => ['nullable','string','max:64'],

            'quantity'     => ['nullable','integer','min:1'],
            'party_size'   => ['nullable','integer','min:1'],

            'bookable_type'=> ['nullable','string','max:120'],
            'bookable_id'  => ['nullable','integer'],

            'status'       => ['required', Rule::in(array_keys(Booking::statusOptions()))],
            'notes'        => ['nullable','string'],

            'meta'         => ['nullable'], // لو بتبعت meta json من form
        ]);
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
     * Confirmations always required.
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

    /**
     * Charge execution fee split once (idempotent) based on service_fees:
     * code = booking_execution_fee
     * rules = {"client_amount":1,"business_amount":1} (amounts)
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

        $fee = $feeService->getByCodeForService('booking_execution_fee', (int)$booking->service_id);
        if (!$fee) {
            return; // no fee configured
        }

        // rules are amounts (recommended)
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
            'code'            => 'booking_execution_fee',
            'client_amount'   => $clientFee,
            'business_amount' => $businessFee,
            'charged_at'      => now()->toDateTimeString(),
        ];

        $booking->meta = $meta;
        $booking->save();
    }
}