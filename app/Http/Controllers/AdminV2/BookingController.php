<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\BusinessServicePrice;
use App\Models\BookableItem;
use App\Services\BookingEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private const EXECUTION_FEE_CODE = 'platform_service_fee';

    // =========================
    // INDEX
    // =========================
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $sort = (string) $request->get('sort', 'starts_at');
        $dir  = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'starts_at', 'ends_at', 'status'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'starts_at';
        }

        $qValue = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $date   = trim((string) $request->get('date', ''));

        $q = Booking::query()
            ->with([
                'user:id,name,phone,email',
                'business:id,name,phone,email',
                'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
                'bookable',
            ]);

        if ($status !== '') {
            $q->where('status', $status);
        }

        if ($date !== '') {
            $q->whereDate('starts_at', $date);
        }

        if ($qValue !== '') {
            $q->where(function ($sub) use ($qValue) {
                if (is_numeric($qValue)) {
                    $sub->orWhere('id', (int) $qValue)
                        ->orWhere('user_id', (int) $qValue)
                        ->orWhere('business_id', (int) $qValue)
                        ->orWhere('service_id', (int) $qValue);
                }

                $sub->orWhere('notes', 'like', "%{$qValue}%");
            });
        }

        $rows = $q->orderBy($sort, $dir)->paginate($perPage)->withQueryString();

        return view('admin-v2.bookings.index', [
            'rows' => $rows,
            'q' => $qValue,
            'status' => $status,
            'date' => $date,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
            'statusOptions' => Booking::statusOptions(),
        ]);
    }

    // =========================
    // CREATE / STORE
    // =========================
    public function create()
    {
        $services = PlatformService::query()
            ->select([
                'id',
                'key',
                'name_ar',
                'name_en',
                'is_active',
                'supports_deposit',
                'max_deposit_percent',
                'fee_type',
                'fee_value',
            ])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $clients = User::query()
            ->select(['id', 'name'])
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '!=', 'business');
            })
            ->orderBy('name')
            ->limit(300)
            ->get();

        return view('admin-v2.bookings.create', [
            'statusOptions' => Booking::statusOptions(),
            'services' => $services,
            'businesses' => $businesses,
            'clients' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateBooking($request, false);

        /** @var BookingEngine $engine */
        $engine = app(BookingEngine::class);

        $calc = $engine->prepare(
            (int) $data['business_id'],
            (int) $data['service_id']
        );

        $bookable = $this->resolveSelectedBookable(
            (int) $data['business_id'],
            (int) $data['service_id'],
            $data['bookable_type'] ?? null,
            $data['bookable_id'] ?? null
        );

        $price = $this->resolveBookingPrice($calc, $bookable);
        $depositPolicy = $this->buildDepositPolicyFromSources($calc, $price, $bookable);

        $data['price'] = $price;

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        $meta['platform_service'] = [
            'id' => (int) $calc['service']->id,
            'key' => (string) $calc['service']->key,
            'name_ar' => (string) ($calc['service']->name_ar ?? ''),
            'name_en' => (string) ($calc['service']->name_en ?? ''),
        ];

        $meta['pricing'] = [
            'price' => (float) $price,
            'platform_fee' => (float) $calc['platform_fee'],
            'fee_type' => (string) ($calc['service']->fee_type ?? ''),
            'fee_value' => $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null,
            'source' => $bookable ? 'bookable_item' : 'business_service_price',
        ];

        $meta['deposit_policy'] = $depositPolicy;

        if ($bookable) {
            $meta['bookable_item'] = [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'code' => (string) ($bookable->code ?? ''),
                'item_type' => (string) ($bookable->item_type ?? ''),
                'price' => (float) $bookable->price,
                'deposit_enabled' => (bool) ($bookable->deposit_enabled ?? false),
                'deposit_percent' => (int) ($bookable->deposit_percent ?? 0),
            ];
        }

        $meta['_execution_fee'] = [
            'code' => self::EXECUTION_FEE_CODE,
            'fee_type' => (string) ($calc['service']->fee_type ?? ''),
            'fee_value' => $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null,
            'client_amount' => 0,
            'business_amount' => 0,
            'platform_amount' => (float) $calc['platform_fee'],
            'charged_at' => null,
        ];

        $data['meta'] = $meta;

        if ($bookable) {
            $data['bookable_type'] = BookableItem::class;
            $data['bookable_id'] = (int) $bookable->id;
        } else {
            $data['bookable_type'] = null;
            $data['bookable_id'] = null;
        }

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
            'business:id,name,code,type',
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
            'bookable',
        ]);

        $deposit = $this->latestDeposit($booking);
        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);
        $depositPolicy = $this->depositPolicy($booking);

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
        $services = PlatformService::query()
            ->select([
                'id',
                'key',
                'name_ar',
                'name_en',
                'is_active',
                'supports_deposit',
                'max_deposit_percent',
                'fee_type',
                'fee_value',
            ])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $clients = User::query()
            ->select(['id', 'name'])
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '!=', 'business');
            })
            ->orderBy('name')
            ->limit(300)
            ->get();

        return view('admin-v2.bookings.edit', [
            'booking' => $booking,
            'statusOptions' => Booking::statusOptions(),
            'services' => $services,
            'businesses' => $businesses,
            'clients' => $clients,
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $oldStatus = (string) $booking->status;
        $data = $this->validateBooking($request, true);

        /** @var BookingEngine $engine */
        $engine = app(BookingEngine::class);

        $serviceId  = (int) ($data['service_id'] ?? $booking->service_id);
        $businessId = (int) ($data['business_id'] ?? $booking->business_id);

        $calc = $engine->prepare($businessId, $serviceId);

        $bookableType = $data['bookable_type'] ?? $booking->bookable_type;
        $bookableId   = $data['bookable_id'] ?? $booking->bookable_id;

        $bookable = $this->resolveSelectedBookable(
            $businessId,
            $serviceId,
            $bookableType,
            $bookableId
        );

        $price = $this->resolveBookingPrice($calc, $bookable);
        $depositPolicy = $this->buildDepositPolicyFromSources($calc, $price, $bookable);

        $data['price'] = $price;

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $newMeta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $meta = array_replace_recursive($meta, $newMeta);

        $meta['platform_service'] = [
            'id' => (int) $calc['service']->id,
            'key' => (string) $calc['service']->key,
            'name_ar' => (string) ($calc['service']->name_ar ?? ''),
            'name_en' => (string) ($calc['service']->name_en ?? ''),
        ];

        $meta['pricing'] = [
            'price' => (float) $price,
            'platform_fee' => (float) $calc['platform_fee'],
            'fee_type' => (string) ($calc['service']->fee_type ?? ''),
            'fee_value' => $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null,
            'source' => $bookable ? 'bookable_item' : 'business_service_price',
        ];

        $meta['deposit_policy'] = $depositPolicy;

        if ($bookable) {
            $meta['bookable_item'] = [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'code' => (string) ($bookable->code ?? ''),
                'item_type' => (string) ($bookable->item_type ?? ''),
                'price' => (float) $bookable->price,
                'deposit_enabled' => (bool) ($bookable->deposit_enabled ?? false),
                'deposit_percent' => (int) ($bookable->deposit_percent ?? 0),
            ];

            $data['bookable_type'] = BookableItem::class;
            $data['bookable_id'] = (int) $bookable->id;
        } else {
            unset($meta['bookable_item']);
            $data['bookable_type'] = null;
            $data['bookable_id'] = null;
        }

        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];
        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['fee_type'] = (string) ($calc['service']->fee_type ?? '');
        $meta['_execution_fee']['fee_value'] = $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null;
        $meta['_execution_fee']['platform_amount'] = (float) $calc['platform_fee'];
        $meta['_execution_fee']['client_amount'] = (float) ($meta['_execution_fee']['client_amount'] ?? 0);
        $meta['_execution_fee']['business_amount'] = (float) ($meta['_execution_fee']['business_amount'] ?? 0);
        $meta['_execution_fee']['charged_at'] = $meta['_execution_fee']['charged_at'] ?? null;

        $data['meta'] = $meta;

        DB::transaction(function () use ($booking, $data, $oldStatus) {
            $booking->fill($data);
            $booking->save();

            $newStatus = (string) $booking->status;

            if ($oldStatus !== Booking::STATUS_IN_PROGRESS && $newStatus === Booking::STATUS_IN_PROGRESS) {
                $deposit = Deposit::query()
                    ->where('target_type', Booking::class)
                    ->where('target_id', (int) $booking->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $depositPolicy = $this->depositPolicy($booking);
                [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

                if (!$clientConfirmed || !$businessConfirmed) {
                    abort(422, 'يجب تأكيد الطرفين قبل بدء التنفيذ.');
                }

                if ($depositPolicy['required']) {
                    if (!$deposit) {
                        abort(422, 'Deposit مطلوب لهذا الحجز قبل بدء التنفيذ.');
                    }

                    if ($deposit->status !== 'frozen') {
                        $deposit->status = 'frozen';
                        $deposit->save();
                    }
                }

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

        return redirect()
            ->route('admin.bookings.index')
            ->with('success', 'تم حذف الحجز.');
    }

    // =========================
    // AJAX service lookup
    // =========================
    public function serviceLookup(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $services = PlatformService::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where('name_ar', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('key', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get([
                'id',
                'key',
                'name_ar',
                'name_en',
                'supports_deposit',
                'max_deposit_percent',
                'fee_type',
                'fee_value',
            ]);

        return response()->json([
            'ok' => true,
            'services' => $services,
        ]);
    }

    public function bookableItemsLookup(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId  = (int) $request->get('service_id', 0);
        $term       = trim((string) $request->get('q', ''));

        $q = BookableItem::query()
            ->where('is_active', 1);

        if ($businessId > 0) {
            $q->where('business_id', $businessId);
        }

        if ($serviceId > 0) {
            $q->where('service_id', $serviceId);
        }

        if ($term !== '') {
            $q->where(function ($sub) use ($term) {
                $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('item_type', 'like', "%{$term}%");
            });
        }

        $rows = $q->orderBy('title')
            ->limit(50)
            ->get([
                'id',
                'business_id',
                'service_id',
                'item_type',
                'title',
                'code',
                'price',
                'capacity',
                'quantity',
                'deposit_enabled',
                'deposit_percent',
            ]);

        return response()->json([
            'ok' => true,
            'items' => $rows,
        ]);
    }

    // =========================
    // Confirmations
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
        return $this->startConfirmClient($booking);
    }

    public function depositConfirmBusiness(Booking $booking)
    {
        return $this->startConfirmBusiness($booking);
    }

    // =========================
    // Deposit actions
    // =========================
    public function depositFreeze(Booking $booking)
    {
        $exists = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', $booking->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Deposit موجود بالفعل.');
        }

        $depositPolicy = $this->depositPolicy($booking);

        if (!$depositPolicy['required']) {
            return back()->with('error', 'Deposit غير مفعل لهذا الحجز.');
        }

        $hold = (float) $depositPolicy['hold'];
        if ($hold <= 0) {
            return back()->with('error', 'قيمة الـ Deposit غير صالحة.');
        }

        $total          = round($hold * 2, 2);
        $clientAmount   = round($hold, 2);
        $businessAmount = round($hold, 2);

        DB::transaction(function () use ($booking, $total, $clientAmount, $businessAmount) {
            Deposit::create([
                'client_id'          => (int) $booking->user_id,
                'business_id'        => (int) $booking->business_id,
                'target_type'        => Booking::class,
                'target_id'          => (int) $booking->id,
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
        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit.');
        }

        $deposit->status = 'released';
        $deposit->released_at = now();
        $deposit->save();

        return back()->with('success', 'تم Release للـ Deposit.');
    }

    public function depositRefund(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit.');
        }

        $deposit->status = 'refunded';
        $deposit->refunded_at = now();
        $deposit->save();

        return back()->with('success', 'تم Refund للـ Deposit.');
    }

    public function depositDisputeOpen(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit.');
        }

        $deposit->status = 'dispute';
        $deposit->dispute_opened_at = now();
        $deposit->dispute_opened_by = 'admin';
        $deposit->save();

        return back()->with('success', 'تم فتح النزاع.');
    }

    public function depositAgreeRelease(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit.');
        }

        $deposit->release_agreed_client = 1;
        $deposit->release_agreed_business = 1;
        $deposit->save();

        return back()->with('success', 'تمت الموافقة على Release.');
    }

    public function depositAgreeRefund(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);
        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit.');
        }

        $deposit->refund_agreed_client = 1;
        $deposit->refund_agreed_business = 1;
        $deposit->save();

        return back()->with('success', 'تمت الموافقة على Refund.');
    }

    // =========================
    // HELPERS
    // =========================
    protected function validateBooking(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],

            'bookable_type' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', Rule::in([BookableItem::class, 'bookable_item', 'bookable_items'])],
            'bookable_id' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer'],

            'starts_at' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date'],
            'ends_at' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date', 'after_or_equal:starts_at'],

            'duration_value' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'duration_unit' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', Rule::in(['minute', 'hour', 'day', 'week', 'month', 'year'])],

            'all_day' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'boolean'],
            'timezone' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:64'],

            'quantity' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'party_size' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],

            'status' => ['required', Rule::in(array_keys(Booking::statusOptions()))],
            'notes' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string'],
        ];

        if (!$isUpdate) {
            $rules['date'] = ['required', 'date'];
            $rules['time'] = ['required'];
        } else {
            $rules['date'] = ['sometimes', 'nullable', 'date'];
            $rules['time'] = ['sometimes', 'nullable'];
        }

        $data = $request->validate($rules);

        if ((empty($data['date']) || empty($data['time'])) && !empty($data['starts_at'])) {
            $dt = Carbon::parse($data['starts_at']);
            $data['date'] = $data['date'] ?? $dt->toDateString();
            $data['time'] = $data['time'] ?? $dt->format('H:i:s');
        }

        if ($isUpdate) {
            if (array_key_exists('date', $data) && $data['date'] === null) {
                unset($data['date']);
            }
            if (array_key_exists('time', $data) && $data['time'] === null) {
                unset($data['time']);
            }
        }

        $data['all_day'] = (int) $request->boolean('all_day');
        $data['quantity'] = (int) ($data['quantity'] ?? 1);

        if (empty($data['timezone'])) {
            $data['timezone'] = 'Africa/Cairo';
        }

        $data['meta'] = is_array($request->input('meta')) ? $request->input('meta') : [];

        $businessId = (int) ($data['business_id'] ?? 0);
        $serviceId  = (int) ($data['service_id'] ?? 0);

        $hasBusinessService = BusinessServicePrice::query()
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->exists();

        if (!$hasBusinessService) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة لهذا البزنس.',
            ]);
        }

        if (!empty($data['bookable_id'])) {
            $bookable = BookableItem::query()
                ->where('id', (int) $data['bookable_id'])
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->first();

            if (!$bookable) {
                throw ValidationException::withMessages([
                    'bookable_id' => 'العنصر القابل للحجز غير موجود أو غير تابع لهذا البزنس/الخدمة.',
                ]);
            }

            $data['bookable_type'] = BookableItem::class;
            $data['bookable_id'] = (int) $bookable->id;
        } else {
            $data['bookable_type'] = null;
            $data['bookable_id'] = null;
        }

        return $data;
    }

    protected function latestDeposit(Booking $booking): ?Deposit
    {
        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();
    }

    protected function resolveConfirmState(Booking $booking, ?Deposit $deposit): array
    {
        if ($deposit) {
            $client = ((int) $deposit->client_confirmed === 1);
            $business = ((int) $deposit->business_confirmed === 1);
            return [$client, $business];
        }

        $meta = $booking->meta ?? [];
        $sc = $meta['_start_confirm'] ?? [];
        $client = !empty($sc['client']);
        $business = !empty($sc['business']);

        return [$client, $business];
    }

    protected function depositPolicy(Booking $booking): array
    {
        $booking->loadMissing([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
            'bookable',
        ]);

        $service = $booking->service;

        $businessPrice = BusinessServicePrice::query()
            ->where('business_id', (int) $booking->business_id)
            ->where('service_id', (int) $booking->service_id)
            ->where('is_active', 1)
            ->first();

        $serviceSupportsDeposit = (bool) data_get($service, 'supports_deposit', false);
        $serviceMaxPercent = (int) data_get($service, 'max_deposit_percent', 0);

        $businessDepositEnabled = (bool) data_get($businessPrice, 'deposit_enabled', false);
        $businessDepositPercent = (int) data_get($businessPrice, 'deposit_percent', 0);

        $bookable = ($booking->bookable instanceof BookableItem) ? $booking->bookable : null;

        $effectiveDepositEnabled = $businessDepositEnabled;
        $effectiveDepositPercent = $businessDepositPercent;
        $source = 'business_service_price';

        if ($bookable && (bool) $bookable->deposit_enabled) {
            $effectiveDepositEnabled = true;
            $effectiveDepositPercent = (int) $bookable->deposit_percent;
            $source = 'bookable_item';
        }

        if ($effectiveDepositPercent > $serviceMaxPercent) {
            $effectiveDepositPercent = $serviceMaxPercent;
        }

        $required = $serviceSupportsDeposit
            && $effectiveDepositEnabled
            && $effectiveDepositPercent > 0;

        $maxAllowed = round(((float) $booking->price) * ($serviceMaxPercent / 100), 2);
        $hold = $required
            ? round(((float) $booking->price) * ($effectiveDepositPercent / 100), 2)
            : 0.00;

        return [
            'required' => $required,
            'hold' => $hold,
            'max' => $maxAllowed,
            'percent' => $serviceMaxPercent,
            'configured_percent' => $effectiveDepositPercent,
            'source' => $source,
            'service_supports_deposit' => $serviceSupportsDeposit,
            'business_deposit_enabled' => $businessDepositEnabled,
            'business_deposit_percent' => $businessDepositPercent,
            'bookable_deposit_enabled' => $bookable ? (bool) $bookable->deposit_enabled : false,
            'bookable_deposit_percent' => $bookable ? (int) $bookable->deposit_percent : 0,
        ];
    }

    protected function chargeExecutionFeeSplitOnce(Booking $booking): void
    {
        $booking->refresh();

        $meta = $booking->meta ?? [];
        if (!empty($meta['_execution_fee']['charged_at'])) {
            return;
        }

        $platformAmount = (float) data_get($meta, '_execution_fee.platform_amount', 0);

        if ($platformAmount <= 0) {
            return;
        }

        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['charged_at'] = now()->toDateTimeString();

        $booking->meta = $meta;
        $booking->save();
    }

    protected function resolveSelectedBookable(
        int $businessId,
        int $serviceId,
        ?string $bookableType,
        $bookableId
    ): ?BookableItem {
        if (empty($bookableId)) {
            return null;
        }

        $normalizedType = $bookableType ?: BookableItem::class;
        $accepted = [BookableItem::class, 'bookable_item', 'bookable_items'];

        if (!in_array($normalizedType, $accepted, true)) {
            return null;
        }

        return BookableItem::query()
            ->where('id', (int) $bookableId)
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->first();
    }

    protected function resolveBookingPrice(array $calc, ?BookableItem $bookable): float
    {
        if ($bookable && (float) $bookable->price > 0) {
            return round((float) $bookable->price, 2);
        }

        return round((float) $calc['price'], 2);
    }

    protected function buildDepositPolicyFromSources(array $calc, float $price, ?BookableItem $bookable): array
    {
        $serviceSupportsDeposit = (bool) ($calc['service']->supports_deposit ?? false);
        $serviceMaxPercent = (int) ($calc['service']->max_deposit_percent ?? 0);

        $businessDepositEnabled = (bool) ($calc['business_price']->deposit_enabled ?? false);
        $businessDepositPercent = (int) ($calc['business_price']->deposit_percent ?? 0);

        $effectiveDepositEnabled = $businessDepositEnabled;
        $effectiveDepositPercent = $businessDepositPercent;
        $source = 'business_service_price';

        if ($bookable && (bool) $bookable->deposit_enabled) {
            $effectiveDepositEnabled = true;
            $effectiveDepositPercent = (int) $bookable->deposit_percent;
            $source = 'bookable_item';
        }

        if ($effectiveDepositPercent > $serviceMaxPercent) {
            throw ValidationException::withMessages([
                'deposit_percent' => "نسبة الديبوزت تتجاوز الحد الأقصى المسموح للخدمة ({$serviceMaxPercent}%).",
            ]);
        }

        $required = $serviceSupportsDeposit
            && $effectiveDepositEnabled
            && $effectiveDepositPercent > 0;

        $amount = $required
            ? round($price * ($effectiveDepositPercent / 100), 2)
            : 0.00;

        return [
            'service_supports_deposit' => $serviceSupportsDeposit,
            'service_max_percent' => $serviceMaxPercent,
            'business_deposit_enabled' => $businessDepositEnabled,
            'business_deposit_percent' => $businessDepositPercent,
            'bookable_deposit_enabled' => $bookable ? (bool) $bookable->deposit_enabled : false,
            'bookable_deposit_percent' => $bookable ? (int) $bookable->deposit_percent : 0,
            'required' => $required,
            'amount' => $amount,
            'source' => $source,
            'configured_percent' => $effectiveDepositPercent,
        ];
    }
}