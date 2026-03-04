<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Service;
use App\Models\User;
use App\Services\DepositsEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    public function __construct(
        private DepositsEscrowService $escrow
    ) {}

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only(['q', 'status', 'date', 'per_page', 'sort', 'dir']);
    }

    /* ==========================================================
     * INDEX
     * ========================================================== */

    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $status  = (string) $request->get('status', '');
        $date    = (string) $request->get('date', ''); // Y-m-d
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $sort = (string) $request->get('sort', 'starts_at');
        $dir  = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['id', 'starts_at', 'ends_at', 'status', 'price', 'created_at'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'starts_at';

        $rows = Booking::query()
            ->with([
                'user:id,name,code,type',
                'business:id,name,code,type,booking_hold_enabled,booking_hold_amount',
                'service:id,business_id,name_ar,name_en,price,duration',
            ])
            ->search($q)
            ->status($status)
            ->when($date !== '', fn ($qq) => $qq->whereDate('starts_at', $date))
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->appends($this->keepQs($request));

        return view('admin-v2.bookings.index', [
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'date' => $date,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,
            'statusOptions' => Booking::statusOptions(),
        ]);
    }

    /* ==========================================================
     * SHOW
     * ========================================================== */

    public function show(Booking $booking)
    {
        $booking = Booking::withTrashed()->findOrFail($booking);
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

        return view('admin-v2.bookings.show', compact('booking', 'deposit'));
    }

    /* ==========================================================
     * CREATE / EDIT
     * ========================================================== */

    public function create()
    {
        $booking = new Booking();
        $booking->timezone = config('app.timezone', 'Africa/Cairo');
        $booking->starts_at = now()->addHour()->format('Y-m-d H:i:s');
        $booking->duration_value = 1;
        $booking->duration_unit = 'hour';
        $booking->status = Booking::STATUS_PENDING;

        $clients = User::query()
            ->select(['id', 'name', 'type', 'code'])
            ->clients()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $businesses = User::query()
            ->select(['id', 'name', 'type', 'code', 'booking_hold_enabled', 'booking_hold_amount'])
            ->businesses()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $services = Service::query()
            ->select(['id', 'business_id', 'name_ar', 'name_en', 'price', 'duration'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        return view('admin-v2.bookings.create', [
            'booking' => $booking,
            'statusOptions' => Booking::statusOptions(),
            'clients' => $clients,
            'businesses' => $businesses,
            'services' => $services,
        ]);
    }

    public function edit(Booking $booking)
    {
        $booking->load([
            'user:id,name,code,type',
            'business:id,name,code,type,booking_hold_enabled,booking_hold_amount',
            'service:id,business_id,name_ar,name_en,price,duration',
        ]);

        $clients = User::query()
            ->select(['id', 'name', 'type', 'code'])
            ->clients()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $businesses = User::query()
            ->select(['id', 'name', 'type', 'code', 'booking_hold_enabled', 'booking_hold_amount'])
            ->businesses()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $services = Service::query()
            ->select(['id', 'business_id', 'name_ar', 'name_en', 'price', 'duration'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        return view('admin-v2.bookings.edit', [
            'booking' => $booking,
            'statusOptions' => Booking::statusOptions(),
            'clients' => $clients,
            'businesses' => $businesses,
            'services' => $services,
        ]);
    }

    /* ==========================================================
     * STORE / UPDATE
     * ========================================================== */

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data = $this->normalizeMetaAndCheckboxes($request, $data);
        $data = $this->applyLegacyDateTime($data);
        $data = $this->hydrateEndTime($data);
        $data = $this->autoPriceFromService($data);

        $booking = Booking::create($data);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم إنشاء الحجز بنجاح');
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $this->validateData($request, $booking);
        $data = $this->normalizeMetaAndCheckboxes($request, $data);

        $oldStatus = (string) $booking->status;
        $newStatus = (string) ($data['status'] ?? $oldStatus);

        $data = $this->applyLegacyDateTime($data);
        $data = $this->hydrateEndTime($data);
        $data = $this->autoPriceFromService($data);

        DB::transaction(function () use ($booking, $data, $oldStatus, $newStatus) {

            // lock booking row
            $booking = Booking::query()
                ->where('id', (int) $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $booking->update($data);

            // ===============================
            // 1) Auto CREATE DEPOSIT when status -> accepted
            // ===============================
            if ($oldStatus !== Booking::STATUS_ACCEPTED && $newStatus === Booking::STATUS_ACCEPTED) {

                $booking->load(['business']);

                $business = $booking->business;

                if ($business && (bool) ($business->booking_hold_enabled ?? false)) {

                    $amount = (float) ($business->booking_hold_amount ?? 0);

                    // لازم يكون فيه طرفين
                    if ($amount > 0 && (int) $booking->user_id > 0 && (int) $booking->business_id > 0) {

                        // totalAmount = amount * 2 (client + business)
                        // percents: 50/50 => each side == amount
                        $this->escrow->create(
                            (int) $booking->user_id,
                            (int) $booking->business_id,
                            (float) ($amount * 2),
                            50,
                            50,
                            Booking::class,
                            (int) $booking->id
                        );
                    }
                }
            }

            // ===============================
            // 2) Auto CHARGE EXECUTION FEE when status -> in_progress
            //    but only after BOTH confirmations on deposit
            //    + make it safe (idempotent flag if exists)
            // ===============================
            if ($oldStatus !== 'in_progress' && $newStatus === 'in_progress') {

                $deposit = Deposit::query()
                    ->where('target_type', Booking::class)
                    ->where('target_id', (int) $booking->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($deposit) {
                    $bothConfirmed = ((int) $deposit->client_confirmed === 1) && ((int) $deposit->business_confirmed === 1);

                    if ($bothConfirmed) {
                        // Option A: column exists (recommended)
                        if (property_exists($deposit, 'execution_fee_charged_at')) {
                            if (empty($deposit->execution_fee_charged_at)) {
                                $this->escrow->chargeExecutionFee($deposit, null, 'DEPOSIT_EXECUTION_FEE');
                                $deposit->execution_fee_charged_at = now();
                                $deposit->save();
                            }
                        } else {
                            // Option B: service must be idempotent by ledger uniqueness
                            $this->escrow->chargeExecutionFee($deposit, null, 'DEPOSIT_EXECUTION_FEE');
                        }
                    }
                }
            }
        });

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم تحديث الحجز بنجاح');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()
            ->route('admin.bookings.index')
            ->with('success', 'تم حذف الحجز بنجاح');
    }

    /* ==========================================================
     * DEPOSIT FREEZE / RELEASE / REFUND
     * ========================================================== */

    public function depositFreeze(Booking $booking)
    {
        // تأكد أنه لا يوجد Deposit مسبق
        $exists = Deposit::where('target_type', Booking::class)
            ->where('target_id', $booking->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Deposit موجود بالفعل.');
        }

        if (!$booking->user_id || !$booking->business_id) {
            return back()->with('error', 'الحجز غير مكتمل (عميل أو بزنس مفقود).');
        }

        DB::transaction(function () use ($booking) {

            Deposit::create([
                'client_id' => $booking->user_id,
                'business_id' => $booking->business_id,
                'target_type' => Booking::class,
                'target_id' => $booking->id,

                'total_amount' => 100,   // مؤقت للاختبار
                'client_percent' => 50,
                'business_percent' => 50,
                'client_amount' => 50,
                'business_amount' => 50,

                'status' => 'frozen',

                'client_confirmed' => false,
                'business_confirmed' => false,
            ]);
        });

        return back()->with('success', 'تم إنشاء Deposit بنجاح.');
    }

    public function depositRelease(Booking $booking)
    {
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();

        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit مرتبط بهذا الحجز');
        }

        $this->escrow->release($deposit);

        return back()->with('success', 'تم Release للـ Deposit');
    }

    public function depositRefund(Booking $booking)
    {
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();

        if (!$deposit) {
            return back()->with('error', 'لا يوجد Deposit مرتبط بهذا الحجز');
        }

        // refund both by default
        $this->escrow->refund($deposit, true, true);

        return back()->with('success', 'تم Refund للـ Deposit');
    }

    /* ==========================================================
     * DEPOSIT CONFIRM ACTIONS
     * ========================================================== */

    public function depositConfirmClient(Booking $booking)
    {
        return $this->depositConfirm($booking, 'client');
    }

    public function depositConfirmBusiness(Booking $booking)
    {
        return $this->depositConfirm($booking, 'business');
    }

    // Backward compatible aliases (لو عندك routes قديمة)
    public function startConfirmClient(Booking $booking)
    {
        return $this->depositConfirm($booking, 'client');
    }

    public function startConfirmBusiness(Booking $booking)
    {
        return $this->depositConfirm($booking, 'business');
    }

    private function depositConfirm(Booking $booking, string $who)
    {
        if (!in_array($who, ['client', 'business'], true)) {
            $who = 'client';
        }

        DB::transaction(function () use ($booking, $who) {

            // lock booking to avoid race with status transitions
            $booking = Booking::query()
                ->where('id', (int) $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $deposit = Deposit::query()
                ->where('target_type', Booking::class)
                ->where('target_id', (int) $booking->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$deposit) {
                throw ValidationException::withMessages([
                    'deposit' => 'لا يوجد Deposit مرتبط بهذا الحجز بعد.',
                ]);
            }

            // منع التأكيد في الحالات النهائية
            $terminalStatuses = ['released', 'refunded', 'cancelled', 'failed'];
            if (in_array((string) $deposit->status, $terminalStatuses, true)) {
                throw ValidationException::withMessages([
                    'deposit' => 'لا يمكن التأكيد لأن حالة الـ Deposit نهائية: ' . $deposit->status,
                ]);
            }

            // لازم الطرفين موجودين على الحجز
            if (!(int) $booking->user_id || !(int) $booking->business_id) {
                throw ValidationException::withMessages([
                    'deposit' => 'لا يمكن تأكيد الـ Deposit بدون client_id و business_id على الحجز.',
                ]);
            }

            $changed = false;

            if ($who === 'client') {
                if ((int) $deposit->client_confirmed === 0) {
                    $deposit->client_confirmed = 1;
                    // optional: $deposit->client_confirmed_at = now();
                    $changed = true;
                }
            } else {
                if ((int) $deposit->business_confirmed === 0) {
                    $deposit->business_confirmed = 1;
                    // optional: $deposit->business_confirmed_at = now();
                    $changed = true;
                }
            }

            if ($changed) {
                $deposit->save();
            }

            // لو الاتنين أكدوا والحجز بالفعل in_progress => اشحن رسوم التنفيذ (بشكل آمن)
            $bothConfirmed = ((int) $deposit->client_confirmed === 1) && ((int) $deposit->business_confirmed === 1);
            if ($bothConfirmed && (string) $booking->status === 'in_progress') {

                if (property_exists($deposit, 'execution_fee_charged_at')) {
                    if (empty($deposit->execution_fee_charged_at)) {
                        $this->escrow->chargeExecutionFee($deposit, null, 'DEPOSIT_EXECUTION_FEE');
                        $deposit->execution_fee_charged_at = now();
                        $deposit->save();
                    }
                } else {
                    // لو مفيش عمود: لازم service تكون idempotent بالـ ledger
                    $this->escrow->chargeExecutionFee($deposit, null, 'DEPOSIT_EXECUTION_FEE');
                }
            }
        });

        return back()->with('success', 'تم تسجيل التأكيد.');
    }

    /* ==========================================================
     * DISPUTE FLOW (NO ADMIN NEEDED)
     * ========================================================== */

    // 1) open dispute (no admin needed)
    public function depositDisputeOpen(Request $request, Booking $booking)
    {
        $who = (string) $request->get('who', 'client'); // client | business
        if (!in_array($who, ['client', 'business'], true)) $who = 'client';

        $reason = trim((string) $request->get('reason', ''));

        DB::transaction(function () use ($booking, $who, $reason) {

            $booking = Booking::query()
                ->where('id', (int) $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            // ensure deposit exists (auto-freeze if missing)
            $deposit = Deposit::query()
                ->where('target_type', Booking::class)
                ->where('target_id', (int) $booking->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$deposit) {
                // يحتاج user_id + business_id
                if (!(int) $booking->user_id || !(int) $booking->business_id) {
                    throw ValidationException::withMessages([
                        'deposit' => 'لا يمكن فتح نزاع بدون client_id و business_id على الحجز.',
                    ]);
                }

                // amount from business settings
                $booking->load(['business']);
                $business = $booking->business;

                $enabled = (bool) ($business->booking_hold_enabled ?? false);
                $amount  = (float) ($business->booking_hold_amount ?? 0);

                if (!$enabled || $amount <= 0) {
                    throw ValidationException::withMessages([
                        'deposit' => 'جدية الحجز غير مفعّلة لهذا البزنس أو المبلغ غير مضبوط.',
                    ]);
                }

                // create deposit: total = amount*2 (50/50)
                $deposit = $this->escrow->create(
                    (int) $booking->user_id,
                    (int) $booking->business_id,
                    (float) ($amount * 2),
                    50,
                    50,
                    Booking::class,
                    (int) $booking->id
                );
            }

            // set dispute + reset agreements (important)
            $deposit->status = 'dispute';
            $deposit->dispute_opened_at = now();
            $deposit->dispute_opened_by = $who;
            $deposit->dispute_reason = $reason !== '' ? $reason : $deposit->dispute_reason;

            $deposit->release_agreed_client = 0;
            $deposit->release_agreed_business = 0;
            $deposit->refund_agreed_client = 0;
            $deposit->refund_agreed_business = 0;

            $deposit->save();
        });

        return back()->with('success', 'تم فتح نزاع وتم إبقاء المبلغ مجمّد.');
    }

    // 2) agree RELEASE (each party clicks)
    public function depositAgreeRelease(Request $request, Booking $booking)
    {
        $who = (string) $request->get('who', 'client');
        if (!in_array($who, ['client', 'business'], true)) $who = 'client';

        DB::transaction(function () use ($booking, $who) {

            $deposit = Deposit::query()
                ->where('target_type', Booking::class)
                ->where('target_id', (int) $booking->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$deposit) {
                throw ValidationException::withMessages(['deposit' => 'لا يوجد Deposit لهذا الحجز.']);
            }

            // must be dispute to use this flow
            if ((string) $deposit->status !== 'dispute') {
                throw ValidationException::withMessages(['deposit' => 'لا يمكن الموافقة على Release إلا أثناء النزاع.']);
            }

            if ($who === 'client') $deposit->release_agreed_client = 1;
            if ($who === 'business') $deposit->release_agreed_business = 1;

            // if someone agrees release => cancel refund agreement for safety
            $deposit->refund_agreed_client = 0;
            $deposit->refund_agreed_business = 0;

            $deposit->save();

            $both = ((int) $deposit->release_agreed_client === 1) && ((int) $deposit->release_agreed_business === 1);
            if ($both) {
                $this->escrow->release($deposit); // service will verify both agreed
            }
        });

        return back()->with('success', 'تم تسجيل الموافقة على Release.');
    }

    // 3) agree REFUND (each party clicks)
    public function depositAgreeRefund(Request $request, Booking $booking)
    {
        $who = (string) $request->get('who', 'client');
        if (!in_array($who, ['client', 'business'], true)) $who = 'client';

        DB::transaction(function () use ($booking, $who) {

            $deposit = Deposit::query()
                ->where('target_type', Booking::class)
                ->where('target_id', (int) $booking->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$deposit) {
                throw ValidationException::withMessages(['deposit' => 'لا يوجد Deposit لهذا الحجز.']);
            }

            if ((string) $deposit->status !== 'dispute') {
                throw ValidationException::withMessages(['deposit' => 'لا يمكن الموافقة على Refund إلا أثناء النزاع.']);
            }

            if ($who === 'client') $deposit->refund_agreed_client = 1;
            if ($who === 'business') $deposit->refund_agreed_business = 1;

            // if someone agrees refund => cancel release agreement for safety
            $deposit->release_agreed_client = 0;
            $deposit->release_agreed_business = 0;

            $deposit->save();

            $both = ((int) $deposit->refund_agreed_client === 1) && ((int) $deposit->refund_agreed_business === 1);
            if ($both) {
                $this->escrow->refund($deposit, true, true); // service will verify both agreed
            }
        });

        return back()->with('success', 'تم تسجيل الموافقة على Refund.');
    }

    /* ==========================================================
     * SERVICE LOOKUP (AJAX)
     * ========================================================== */

    public function serviceLookup(Request $request)
    {
        $id = (int) $request->get('id', 0);

        if ($id <= 0) {
            return response()->json(['ok' => false, 'message' => 'Invalid id'], 400);
        }

        $service = Service::query()
            ->select(['id', 'business_id', 'name_ar', 'name_en', 'price', 'duration'])
            ->find($id);

        if (!$service) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $service->id,
                'business_id' => $service->business_id,
                'name' => $service->name_ar ?: ($service->name_en ?: ('Service #' . $service->id)),
                'price' => (float) $service->price,
                'duration_minutes' => (int) $service->duration,
            ],
        ]);
    }

    /* ==========================================================
     * VALIDATION + META + TIME + PRICE
     * ========================================================== */

    private function validateData(Request $request, ?Booking $booking = null): array
    {
        $statusKeys = array_keys(Booking::statusOptions());

        $rules = [
            'user_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->whereIn('type', ['client', 'business']),
            ],

            'business_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where('type', 'business'),
            ],

            'service_id'  => ['nullable', 'integer', 'min:1'],

            'starts_at' => ['required', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],

            'duration_value' => ['nullable', 'integer', 'min:1'],
            'duration_unit'  => ['nullable', Rule::in(['minute', 'hour', 'day', 'week', 'month', 'year'])],

            'all_day'   => ['nullable'],
            'timezone'  => ['nullable', 'string', 'max:64'],

            'quantity'   => ['nullable', 'integer', 'min:1'],
            'party_size' => ['nullable', 'integer', 'min:1'],

            'price'  => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in($statusKeys)],
            'notes'  => ['nullable', 'string', 'max:5000'],

            'meta_raw' => ['nullable', 'string', 'max:20000'],
        ];

        $data = $request->validate($rules);

        // ===== Extra validation by DB (types + relations) =====
        $userId     = (int) ($data['user_id'] ?? 0);
        $businessId = (int) ($data['business_id'] ?? 0);
        $serviceId  = (int) ($data['service_id'] ?? 0);

        if ($userId > 0 && $businessId > 0 && $userId === $businessId) {
            throw ValidationException::withMessages([
                'user_id' => 'لا يمكن أن يكون العميل هو نفس حساب الـ Business.',
            ]);
        }

        // business must be type=business
        $business = User::query()
            ->select(['id', 'type'])
            ->where('id', $businessId)
            ->first();

        if (!$business || (string) $business->type !== 'business') {
            throw ValidationException::withMessages([
                'business_id' => 'Business غير صالح (يجب أن يكون type=business).',
            ]);
        }

        // user (client) must be type=client (if provided)
        if ($userId > 0) {
            $client = User::query()
                ->select(['id', 'type'])
                ->where('id', $userId)
                ->first();

            if (!$client || (string) $client->type !== 'client') {
                throw ValidationException::withMessages([
                    'user_id' => 'Client غير صالح (يجب أن يكون type=client).',
                ]);
            }
        }

        // service must belong to selected business (if provided)
        if ($serviceId > 0) {
            $service = Service::query()
                ->select(['id', 'business_id'])
                ->where('id', $serviceId)
                ->first();

            if (!$service) {
                throw ValidationException::withMessages([
                    'service_id' => 'Service غير موجودة.',
                ]);
            }

            if ((int) $service->business_id !== $businessId) {
                throw ValidationException::withMessages([
                    'service_id' => 'هذه الخدمة لا تتبع الـ Business المحدد.',
                ]);
            }
        }

        return $data;
    }

    private function normalizeMetaAndCheckboxes(Request $request, array $data): array
    {
        // checkbox normalize
        $data['all_day'] = $request->has('all_day');

        // meta parsing
        $metaRaw = trim((string) ($data['meta_raw'] ?? ''));
        unset($data['meta_raw']);

        if ($metaRaw !== '') {
            $decoded = json_decode($metaRaw, true);
            $data['meta'] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : ['_raw' => $metaRaw];
        } else {
            $data['meta'] = null;
        }

        return $data;
    }

    private function applyLegacyDateTime(array $data): array
    {
        // لو عندك أعمدة legacy date/time في الجدول
        if (!empty($data['starts_at'])) {
            $start = \Carbon\Carbon::parse($data['starts_at']);
            $data['date'] = $start->toDateString();
            $data['time'] = $start->format('H:i:s');
        }

        return $data;
    }

    private function hydrateEndTime(array $data): array
    {
        $ends = trim((string) ($data['ends_at'] ?? ''));
        if ($ends !== '') return $data;

        $val  = (int) ($data['duration_value'] ?? 0);
        $unit = (string) ($data['duration_unit'] ?? '');

        if ($val <= 0 || $unit === '') return $data;

        $start = \Carbon\Carbon::parse($data['starts_at']);

        $end = match ($unit) {
            'minute' => $start->copy()->addMinutes($val),
            'hour'   => $start->copy()->addHours($val),
            'day'    => $start->copy()->addDays($val),
            'week'   => $start->copy()->addWeeks($val),
            'month'  => $start->copy()->addMonths($val),
            'year'   => $start->copy()->addYears($val),
            default  => null,
        };

        if ($end) $data['ends_at'] = $end->format('Y-m-d H:i:s');

        return $data;
    }

    private function durationToMinutes(?int $val, ?string $unit): ?int
    {
        $val = (int) ($val ?? 0);
        $unit = (string) ($unit ?? '');
        if ($val <= 0 || $unit === '') return null;

        return match ($unit) {
            'minute' => $val,
            'hour'   => $val * 60,
            'day'    => $val * 60 * 24,
            'week'   => $val * 60 * 24 * 7,
            'month'  => $val * 60 * 24 * 30,
            'year'   => $val * 60 * 24 * 365,
            default  => null,
        };
    }

    private function autoPriceFromService(array $data): array
    {
        $serviceId = (int) ($data['service_id'] ?? 0);
        if ($serviceId <= 0) return $data;

        $service = Service::query()->select(['id', 'price', 'duration'])->find($serviceId);
        if (!$service) return $data;

        $basePrice   = (float) ($service->price ?? 0);
        $baseMinutes = (int) ($service->duration ?? 0);
        if ($basePrice <= 0 || $baseMinutes <= 0) return $data;

        $bookingMinutes = null;

        // ends_at موجود
        if (!empty($data['ends_at']) && !empty($data['starts_at'])) {
            $start = \Carbon\Carbon::parse($data['starts_at']);
            $end   = \Carbon\Carbon::parse($data['ends_at']);
            $diff  = $start->diffInMinutes($end, false);
            if ($diff > 0) $bookingMinutes = $diff;
        }

        // duration موجود
        if ($bookingMinutes === null) {
            $bookingMinutes = $this->durationToMinutes(
                isset($data['duration_value']) ? (int) $data['duration_value'] : null,
                isset($data['duration_unit']) ? (string) $data['duration_unit'] : null
            );
        }

        if (!$bookingMinutes || $bookingMinutes <= 0) {
            $data['price'] = number_format($basePrice, 2, '.', '');
            return $data;
        }

        $ratio = $bookingMinutes / $baseMinutes;
        $price = $basePrice * $ratio;

        $data['price'] = number_format($price, 2, '.', '');

        // (اختياري) حفظ تفاصيل التسعير في meta
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $meta['_pricing'] = [
            'base_price' => $basePrice,
            'base_minutes' => $baseMinutes,
            'booking_minutes' => $bookingMinutes,
            'ratio' => $ratio,
        ];
        $data['meta'] = $meta ?: null;

        return $data;
    }
    public function disputesIndex()
    {
        $rows = \App\Models\Deposit::query()
            ->where('status', 'dispute')
            ->with([
                'booking.user',
                'booking.business',
            ])
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin-v2.disputes.index', compact('rows'));
    }
}