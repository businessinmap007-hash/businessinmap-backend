<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\BusinessServicePrice;
use App\Models\BookableItem;
use App\Models\CategoryChildServiceFee;
use App\Services\BookingDepositService;
use App\Services\BookingEngine;
use App\Services\WalletFeeService;
use App\Services\BookableAvailabilityService;
use App\Services\BookablePricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private const EXECUTION_FEE_CODE = 'booking_execution';

    public function __construct(
        protected BookingDepositService $bookingDepositService
    ) {
    }

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
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'starts_at';
        }

        $qValue = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $date   = trim((string) $request->get('date', ''));

        $query = Booking::query()
            ->with([
                'user:id,name,phone,email',
                'business:id,name,phone,email,category_id,category_child_id',
                'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
                'bookable',
            ]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($date !== '') {
            $query->whereDate('starts_at', $date);
        }

        if ($qValue !== '') {
            $query->where(function ($sub) use ($qValue) {
                if (is_numeric($qValue)) {
                    $numeric = (int) $qValue;

                    $sub->orWhere('id', $numeric)
                        ->orWhere('user_id', $numeric)
                        ->orWhere('business_id', $numeric)
                        ->orWhere('service_id', $numeric);
                }

                $sub->orWhere('notes', 'like', "%{$qValue}%");
            });
        }

        $rows = $query
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

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
        return view('admin-v2.bookings.create', $this->formData());
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

        $calc = $this->enrichCalcWithContext(
            $calc,
            (int) $data['business_id'],
            (int) $data['service_id']
        );

        $bookable = $this->resolveSelectedBookable(
            (int) $data['business_id'],
            (int) $data['service_id'],
            $data['bookable_type'] ?? null,
            $data['bookable_id'] ?? null
        );

        $priceBreakdown = $this->resolveBookingPriceBreakdown(
            $calc,
            $bookable,
            (int) ($data['quantity'] ?? 1)
        );

        $depositPolicy = $this->buildDepositPolicyFromSources(
            $calc,
            (float) $priceBreakdown['final_price'],
            $bookable
        );

        $data['price'] = (float) $priceBreakdown['final_price'];
        $data = $this->applyBookableToPayload($data, $bookable);
        $data['meta'] = $this->buildBookingMeta(
            existingMeta: [],
            calc: $calc,
            priceBreakdown: $priceBreakdown,
            depositPolicy: $depositPolicy,
            bookable: $bookable
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
            'business:id,name,code,type,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
            'bookable',
            'latestDispute',
        ]);

        $deposit = $this->latestDeposit($booking);
        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);
        $depositPolicy = $this->depositPolicy($booking);
        $latestDispute = $booking->latestDispute;

        return view('admin-v2.bookings.show', compact(
            'booking',
            'deposit',
            'clientConfirmed',
            'businessConfirmed',
            'depositPolicy',
            'latestDispute'
        ));
    }

    // =========================
    // EDIT / UPDATE
    // =========================
    public function edit(Booking $booking)
    {
        return view('admin-v2.bookings.edit', array_merge(
            $this->formData(),
            ['booking' => $booking]
        ));
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
        $calc = $this->enrichCalcWithContext($calc, $businessId, $serviceId);

        $bookableType = $data['bookable_type'] ?? $booking->bookable_type;
        $bookableId   = $data['bookable_id'] ?? $booking->bookable_id;

        $bookable = $this->resolveSelectedBookable(
            $businessId,
            $serviceId,
            $bookableType,
            $bookableId
        );

        $priceBreakdown = $this->resolveBookingPriceBreakdown(
            $calc,
            $bookable,
            (int) ($data['quantity'] ?? $booking->quantity ?? 1)
        );

        $depositPolicy = $this->buildDepositPolicyFromSources(
            $calc,
            (float) $priceBreakdown['final_price'],
            $bookable
        );

        $data['price'] = (float) $priceBreakdown['final_price'];
        $data = $this->applyBookableToPayload($data, $bookable);

        $existingMeta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $incomingMeta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $mergedMeta   = array_replace_recursive($existingMeta, $incomingMeta);

        $data['meta'] = $this->buildBookingMeta(
            existingMeta: $mergedMeta,
            calc: $calc,
            priceBreakdown: $priceBreakdown,
            depositPolicy: $depositPolicy,
            bookable: $bookable
        );

        DB::transaction(function () use ($booking, $data, $oldStatus) {
            $booking->fill($data);
            $booking->save();

            $newStatus = (string) $booking->status;

            if ($oldStatus !== Booking::STATUS_IN_PROGRESS && $newStatus === Booking::STATUS_IN_PROGRESS) {
                $this->handleMoveToInProgress($booking);
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
    // AJAX LOOKUPS
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

        $query = BookableItem::query()->where('is_active', 1);

        if ($businessId > 0) {
            $query->where('business_id', $businessId);
        }

        if ($serviceId > 0) {
            $query->where('service_id', $serviceId);
        }

        if ($term !== '') {
            $query->where(function ($sub) use ($term) {
                $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('item_type', 'like', "%{$term}%");
            });
        }

        $rows = $query
            ->orderBy('title')
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

    public function pricingPreview(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId  = (int) $request->get('service_id', 0);
        $bookableId = (int) $request->get('bookable_id', 0);
        $quantity   = max((int) $request->get('quantity', 1), 1);

        $startsAt = $request->get('starts_at');
        $endsAt   = $request->get('ends_at');

        if ($businessId <= 0 || $serviceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'business_id و service_id مطلوبان',
            ], 422);
        }

        /** @var BookingEngine $engine */
        $engine = app(BookingEngine::class);

        /** @var BookableAvailabilityService $availabilityService */
        $availabilityService = app(BookableAvailabilityService::class);

        /** @var BookablePricingService $pricingService */
        $pricingService = app(BookablePricingService::class);

        $calc = $engine->prepare($businessId, $serviceId);
        $calc = $this->enrichCalcWithContext($calc, $businessId, $serviceId);

        $bookable = null;
        $availability = null;
        $dynamicBookablePricing = null;

        if ($bookableId > 0) {
            $bookable = BookableItem::query()
                ->where('id', $bookableId)
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->first();

            if (! $bookable) {
                return response()->json([
                    'ok' => false,
                    'message' => 'العنصر القابل للحجز غير موجود أو غير نشط',
                ], 404);
            }

            if (! empty($startsAt) && ! empty($endsAt)) {
                try {
                    $availability = $availabilityService->check($bookable, $startsAt, $endsAt);
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'ok' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                } catch (\Throwable $e) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'تعذر فحص التوفر',
                    ], 500);
                }

                if (! ($availability['available'] ?? false)) {
                    return response()->json([
                        'ok' => false,
                        'message' => $availability['reason'] ?? 'العنصر غير متاح في الفترة المحددة',
                        'code' => $availability['code'] ?? 'unavailable',
                        'availability' => [
                            'available' => false,
                            'starts_at' => $availability['starts_at'] ?? $startsAt,
                            'ends_at' => $availability['ends_at'] ?? $endsAt,
                            'conflicts_count' => isset($availability['conflicts']) ? $availability['conflicts']->count() : 0,
                            'conflicts' => isset($availability['conflicts'])
                                ? $availability['conflicts']->map(function ($slot) {
                                    return [
                                        'id' => (int) $slot->id,
                                        'block_type' => (string) ($slot->block_type ?? ''),
                                        'reason' => (string) ($slot->reason ?? ''),
                                        'starts_at' => optional($slot->starts_at)->toDateTimeString(),
                                        'ends_at' => optional($slot->ends_at)->toDateTimeString(),
                                    ];
                                })->values()
                                : [],
                        ],
                    ], 422);
                }
            }

            try {
                $pricingDate = ! empty($startsAt) ? $startsAt : now();

                $dynamicBookablePricing = $pricingService->resolve(
                    $bookable,
                    $pricingDate,
                    $quantity
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                ], 422);
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'message' => 'تعذر حساب سعر العنصر القابل للحجز',
                ], 500);
            }
        }

        $priceBreakdown = $this->resolveBookingPriceBreakdown($calc, $bookable, $quantity);

        if ($dynamicBookablePricing && ($dynamicBookablePricing['ok'] ?? false)) {
            $priceBreakdown['base_price'] = (float) ($dynamicBookablePricing['base_price'] ?? 0);
            $priceBreakdown['unit_price'] = (float) ($dynamicBookablePricing['unit_price'] ?? 0);
            $priceBreakdown['final_price'] = (float) ($dynamicBookablePricing['final_price'] ?? 0);
            $priceBreakdown['currency'] = (string) ($dynamicBookablePricing['currency'] ?? 'EGP');
            $priceBreakdown['bookable_rule'] = $dynamicBookablePricing['rule'] ?? null;
            $priceBreakdown['bookable_breakdown'] = $dynamicBookablePricing['breakdown'] ?? [];
            $priceBreakdown['pricing_source'] = 'bookable_price_rules';
        } else {
            $priceBreakdown['pricing_source'] = 'default_booking_engine';
        }

        $depositPolicy = $this->buildDepositPolicyFromSources(
            $calc,
            (float) ($priceBreakdown['final_price'] ?? 0),
            $bookable
        );

        return response()->json([
            'ok' => true,
            'service' => [
                'id' => (int) $calc['service']->id,
                'key' => (string) ($calc['service']->key ?? ''),
                'name_ar' => (string) ($calc['service']->name_ar ?? ''),
                'name_en' => (string) ($calc['service']->name_en ?? ''),
                'supports_deposit' => (bool) ($calc['service']->supports_deposit ?? false),
                'max_deposit_percent' => (int) ($calc['service']->max_deposit_percent ?? 0),
                'fee_type' => (string) ($calc['service']->fee_type ?? ''),
                'fee_value' => $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null,
            ],
            'business_price' => [
                'price' => (float) ($calc['business_price']->price ?? 0),
                'discount_enabled' => (bool) ($calc['business_price']->discount_enabled ?? false),
                'discount_percent' => (int) ($calc['business_price']->discount_percent ?? 0),
                'deposit_enabled' => (bool) ($calc['business_price']->deposit_enabled ?? false),
                'deposit_percent' => (int) ($calc['business_price']->deposit_percent ?? 0),
                'child_id' => (int) (($calc['business_price']->child_id ?? 0)),
            ],
            'bookable' => $bookable ? [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'price' => (float) $bookable->price,
                'deposit_enabled' => (bool) ($bookable->deposit_enabled ?? false),
                'deposit_percent' => (int) ($bookable->deposit_percent ?? 0),
                'capacity' => $bookable->capacity !== null ? (int) $bookable->capacity : null,
                'quantity' => $bookable->quantity !== null ? (int) $bookable->quantity : null,
            ] : null,
            'fee_snapshot' => $calc['fee_snapshot'] ?? [],
            'availability' => $availability ? [
                'available' => (bool) ($availability['available'] ?? false),
                'code' => (string) ($availability['code'] ?? ''),
                'reason' => $availability['reason'] ?? null,
                'starts_at' => $availability['starts_at'] ?? $startsAt,
                'ends_at' => $availability['ends_at'] ?? $endsAt,
            ] : null,
            'bookable_dynamic_pricing' => $dynamicBookablePricing,
            'pricing' => $priceBreakdown,
            'deposit_policy' => $depositPolicy,
        ]);
    }

    // =========================
    // START CONFIRMATIONS
    // =========================
    public function startConfirmClient(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->client_confirmed = true;
                $deposit->save();

                return;
            }

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
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
                $deposit->business_confirmed = true;
                $deposit->save();

                return;
            }

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
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
    // DEPOSIT ACTIONS
    // =========================
    public function depositFreeze(Booking $booking)
    {
        $depositPolicy = $this->depositPolicy($booking);

        if (! $depositPolicy['required']) {
            return back()->with('error', 'Deposit غير مفعل لهذا الحجز.');
        }

        try {
            $this->bookingDepositService->freezeForBooking(
                $booking,
                (float) $depositPolicy['hold']
            );

            return back()->with('success', 'تم إنشاء Deposit وتجميد المبالغ بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر إنشاء الـ Deposit: ' . $e->getMessage());
        }
    }

    public function depositRelease(Booking $booking)
    {
        try {
            $this->bookingDepositService->releaseForBooking($booking);

            return back()->with('success', 'تم Release للـ Deposit بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر Release للـ Deposit: ' . $e->getMessage());
        }
    }

    public function depositRefund(Booking $booking)
    {
        try {
            $this->bookingDepositService->refundForBooking($booking);

            return back()->with('success', 'تم Refund للـ Deposit بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر Refund للـ Deposit: ' . $e->getMessage());
        }
    }

    public function depositDisputeOpen(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:100'],
            'reason_text' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $dispute = $this->bookingDepositService->openDisputeForBooking(
                booking: $booking,
                openedByUserId: auth()->id() ?: (int) $booking->user_id,
                actorId: auth()->id(),
                payload: [
                    'reason_code' => $data['reason_code'] ?? null,
                    'reason_text' => $data['reason_text'] ?? null,
                ]
            );

            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->with('success', 'تم فتح النزاع بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر فتح النزاع: ' . $e->getMessage());
        }
    }

    public function depositAgreeRelease(Booking $booking)
    {
        try {
            $this->bookingDepositService->releaseForBooking($booking);

            return back()->with('success', 'تمت الموافقة وتنفيذ Release.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر تنفيذ Release: ' . $e->getMessage());
        }
    }

    public function depositAgreeRefund(Booking $booking)
    {
        try {
            $this->bookingDepositService->refundForBooking($booking);

            return back()->with('success', 'تمت الموافقة وتنفيذ Refund.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تعذر تنفيذ Refund: ' . $e->getMessage());
        }
    }

    // =========================
    // HELPERS
    // =========================
    protected function formData(): array
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
            ->select(['id', 'name', 'category_child_id'])
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

        $businessServicePrices = BusinessServicePrice::query()
            ->select([
                'business_id',
                'child_id',
                'service_id',
                'price',
                'deposit_enabled',
                'deposit_percent',
                'discount_enabled',
                'discount_percent',
            ])
            ->get();

        return [
            'statusOptions' => Booking::statusOptions(),
            'services' => $services,
            'businesses' => $businesses,
            'clients' => $clients,
            'businessServicePrices' => $businessServicePrices,
        ];
    }

    protected function validateBooking(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],

            'bookable_type' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', Rule::in([BookableItem::class, 'bookable_item', 'bookable_items'])],
            'bookable_id' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer'],

            'starts_at' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date'],
            'ends_at' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'date', 'after:starts_at'],

            'duration_value' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'duration_unit' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', Rule::in(['minute', 'hour', 'day', 'week', 'month', 'year'])],

            'all_day' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'boolean'],
            'timezone' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:64'],

            'quantity' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],
            'party_size' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:1'],

            'status' => ['required', Rule::in(array_keys(Booking::statusOptions()))],
            'notes' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string'],

            'date' => [$isUpdate ? 'sometimes' : 'required', 'nullable', 'date'],
            'time' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable'],
        ];

        $data = $request->validate($rules);

        $data['all_day'] = (int) $request->boolean('all_day');
        $data['quantity'] = max((int) ($data['quantity'] ?? 1), 1);

        if (empty($data['timezone'])) {
            $data['timezone'] = 'Africa/Cairo';
        }

        $data['meta'] = is_array($request->input('meta')) ? $request->input('meta') : [];

        $durationUnit  = (string) ($data['duration_unit'] ?? 'day');
        $durationValue = max((int) ($data['duration_value'] ?? $data['quantity'] ?? 1), 1);

        $date = $data['date'] ?? null;
        $time = $data['time'] ?? null;

        if (! empty($data['starts_at'])) {
            $start = Carbon::parse($data['starts_at'], $data['timezone']);
        } elseif ($date) {
            if ($durationUnit === 'day') {
                $start = Carbon::parse($date . ' 00:00:00', $data['timezone']);
                $data['all_day'] = 1;
            } else {
                $startTime = $time ?: '00:00';
                $start = Carbon::parse($date . ' ' . $startTime, $data['timezone']);
            }
        } else {
            $start = null;
        }

        if ($start) {
            if (! empty($data['ends_at'])) {
                $end = Carbon::parse($data['ends_at'], $data['timezone']);
            } else {
                $end = match ($durationUnit) {
                    'day' => (clone $start)->addDays($durationValue),
                    'hour' => (clone $start)->addHours($durationValue),
                    'minute' => (clone $start)->addMinutes($durationValue),
                    'week' => (clone $start)->addWeeks($durationValue),
                    'month' => (clone $start)->addMonths($durationValue),
                    'year' => (clone $start)->addYears($durationValue),
                    default => null,
                };
            }

            $data['starts_at'] = $start->copy()->utc();
            $data['ends_at'] = $end ? $end->copy()->utc() : null;
            $data['date'] = $start->toDateString();
            $data['time'] = $start->format('H:i:s');

            if ($end) {
                if ($durationUnit === 'day') {
                    $data['duration_value'] = $start->diffInDays($end);
                } elseif ($durationUnit === 'hour') {
                    $data['duration_value'] = max(1, $start->diffInHours($end));
                } elseif ($durationUnit === 'minute') {
                    $data['duration_value'] = max(1, $start->diffInMinutes($end));
                } else {
                    $data['duration_value'] = $durationValue;
                }
            } else {
                $data['duration_value'] = $durationValue;
            }

            $data['duration_unit'] = $durationUnit;
        }

        if ($isUpdate) {
            if (array_key_exists('date', $data) && $data['date'] === null) {
                unset($data['date']);
            }

            if (array_key_exists('time', $data) && $data['time'] === null) {
                unset($data['time']);
            }
        }

        $businessId = (int) ($data['business_id'] ?? 0);
        $serviceId  = (int) ($data['service_id'] ?? 0);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود.',
            ]);
        }

        $businessService = $this->resolveBusinessServicePrice(
            $businessId,
            $serviceId,
            $childId
        );

        if (! $businessService) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير متاحة لهذا البزنس داخل هذا القسم الفرعي.',
            ]);
        }

        if (! $businessService) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة لهذا البزنس ضمن القسم الفرعي الحالي.',
            ]);
        }

        if (! empty($data['bookable_id'])) {
            $bookable = BookableItem::query()
                ->where('id', (int) $data['bookable_id'])
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->first();

            if (! $bookable) {
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
        return $this->bookingDepositService->latestDeposit($booking);
    }

    protected function resolveConfirmState(Booking $booking, ?Deposit $deposit): array
    {
        if ($deposit) {
            return [
                (bool) $deposit->client_confirmed,
                (bool) $deposit->business_confirmed,
            ];
        }

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $sc = $meta['_start_confirm'] ?? [];

        return [
            ! empty($sc['client']),
            ! empty($sc['business']),
        ];
    }

    protected function depositPolicy(Booking $booking): array
    {
        $booking->loadMissing([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
            'business:id,name,category_id,category_child_id',
            'bookable',
        ]);

        $service = $booking->service;
        $childId = (int) ($booking->business?->category_child_id ?? 0);

        $businessPrice = $this->resolveBusinessServicePrice(
            (int) $booking->business_id,
            (int) $booking->service_id,
            $childId
        );

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

    protected function handleMoveToInProgress(Booking $booking): void
    {
        $deposit = $this->latestDeposit($booking);
        $depositPolicy = $this->depositPolicy($booking);
        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

        if (! $clientConfirmed || ! $businessConfirmed) {
            throw ValidationException::withMessages([
                'status' => 'يجب تأكيد الطرفين قبل بدء التنفيذ.',
            ]);
        }

        if ($depositPolicy['required']) {
            if (! $deposit) {
                throw ValidationException::withMessages([
                    'status' => 'Deposit مطلوب لهذا الحجز قبل بدء التنفيذ.',
                ]);
            }

            if (! $deposit->isFrozen()) {
                throw ValidationException::withMessages([
                    'status' => 'يجب أن تكون حالة الـ Deposit مجمدة قبل بدء التنفيذ.',
                ]);
            }
        }

        $this->chargeExecutionFeeSplitOnce($booking);
    }

    protected function chargeExecutionFeeSplitOnce(Booking $booking): void
    {
        $booking->refresh();

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];

        if (! empty($meta['_execution_fee']['charged_at'])) {
            return;
        }

        /** @var WalletFeeService $feeService */
        $feeService = app(WalletFeeService::class);

        /*
        |----------------------------------------------------------------------
        | ملاحظة:
        | WalletFeeService ما زال هو المنفذ الفعلي للخصم.
        | هنا فقط نحافظ على snapshot أوضح داخل booking meta.
        |----------------------------------------------------------------------
        */
        $transactions = $feeService->applyBookingFees($booking, self::EXECUTION_FEE_CODE);

        $clientAmount = 0.0;
        $businessAmount = 0.0;
        $txMap = [];

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $payer  = (string) data_get($tx->meta, 'payer', '');

            if ($payer === 'client') {
                $clientAmount += $amount;
            } elseif ($payer === 'business') {
                $businessAmount += $amount;
            }

            $txMap[] = [
                'id' => (int) $tx->id,
                'user_id' => (int) $tx->user_id,
                'payer' => $payer,
                'amount' => $amount,
                'type' => (string) $tx->type,
                'direction' => (string) $tx->direction,
                'status' => (string) ($tx->status ?? ''),
            ];
        }

        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['client_amount'] = round($clientAmount, 2);
        $meta['_execution_fee']['business_amount'] = round($businessAmount, 2);
        $meta['_execution_fee']['charged_at'] = now()->toDateTimeString();
        $meta['_execution_fee']['transactions'] = $txMap;

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

        if (! in_array($normalizedType, $accepted, true)) {
            return null;
        }

        return BookableItem::query()
            ->where('id', (int) $bookableId)
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->first();
    }

    protected function applyBookableToPayload(array $data, ?BookableItem $bookable): array
    {
        if ($bookable) {
            $data['bookable_type'] = BookableItem::class;
            $data['bookable_id'] = (int) $bookable->id;
        } else {
            $data['bookable_type'] = null;
            $data['bookable_id'] = null;
        }

        return $data;
    }

    protected function resolveBookingPriceBreakdown(array $calc, ?BookableItem $bookable, int $quantity = 1): array
    {
        $quantity = max($quantity, 1);

        if ($bookable && (float) $bookable->price > 0) {
            $unitPrice = round((float) $bookable->price, 2);
            $originalPrice = round($unitPrice * $quantity, 2);
            $feeSnapshot = $calc['fee_snapshot'] ?? [];
            $platformFee = (float) ($calc['platform_fee'] ?? 0);

            return [
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'original_price' => $originalPrice,
                'discount_enabled' => false,
                'discount_percent' => 0,
                'discount_amount' => 0.00,
                'final_price' => $originalPrice,
                'platform_fee' => round($platformFee * $quantity, 2),
            ];
        }

        $businessPrice = $calc['business_price'] ?? null;

        $unitPrice = round((float) ($businessPrice->price ?? $calc['price'] ?? 0), 2);
        $originalPrice = round($unitPrice * $quantity, 2);

        $discountEnabled = (bool) ($businessPrice->discount_enabled ?? false);
        $discountPercent = $discountEnabled ? (int) ($businessPrice->discount_percent ?? 0) : 0;
        $discountPercent = max(0, min($discountPercent, 100));

        $discountAmount = $discountEnabled
            ? round($originalPrice * ($discountPercent / 100), 2)
            : 0.00;

        $finalPrice = round($originalPrice - $discountAmount, 2);
        if ($finalPrice < 0) {
            $finalPrice = 0.00;
        }

        $platformFee = (float) ($calc['platform_fee'] ?? 0);

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'original_price' => $originalPrice,
            'discount_enabled' => $discountEnabled,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'platform_fee' => $platformFee,
        ];
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

    protected function buildBookingMeta(
        array $existingMeta,
        array $calc,
        array $priceBreakdown,
        array $depositPolicy,
        ?BookableItem $bookable
    ): array {
        $meta = $existingMeta;
        $feeSnapshot = $calc['fee_snapshot'] ?? [];
        $businessPrice = $calc['business_price'] ?? null;

        $meta['platform_service'] = [
            'id' => (int) $calc['service']->id,
            'key' => (string) $calc['service']->key,
            'name_ar' => (string) ($calc['service']->name_ar ?? ''),
            'name_en' => (string) ($calc['service']->name_en ?? ''),
        ];

        $meta['business_context'] = [
            'business_id' => (int) ($calc['business']->id ?? 0),
            'category_id' => (int) ($calc['business']->category_id ?? 0),
            'category_child_id' => (int) ($calc['business']->category_child_id ?? 0),
        ];
  
        $meta['pricing'] = [
            'original_price' => (float) $priceBreakdown['original_price'],
            'discount_enabled' => (bool) $priceBreakdown['discount_enabled'],
            'discount_percent' => (int) $priceBreakdown['discount_percent'],
            'discount_amount' => (float) $priceBreakdown['discount_amount'],
            'final_price' => (float) $priceBreakdown['final_price'],
            'price' => (float) $priceBreakdown['final_price'],
            'fee_type' => (string) ($calc['service']->fee_type ?? ''),
            'fee_value' => $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null,
            'source' => $bookable ? 'bookable_item' : 'business_service_price',
            'unit_price' => (float) $priceBreakdown['unit_price'],
            'quantity' => (int) $priceBreakdown['quantity'],
            'platform_fee' => (float) $priceBreakdown['platform_fee'],
            'business_service_price_id' => (int) ($businessPrice->id ?? 0),
            'business_service_price_child_id' => (int) ($businessPrice->child_id ?? 0),
        ];

        $meta['service_fees_snapshot'] = $feeSnapshot;
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
        } else {
            unset($meta['bookable_item']);
        }

        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];
        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['fee_type'] = (string) ($calc['service']->fee_type ?? '');
        $meta['_execution_fee']['fee_value'] = $calc['service']->fee_value !== null ? (float) $calc['service']->fee_value : null;
        $meta['_execution_fee']['platform_amount'] = (float) $priceBreakdown['platform_fee'];
        $meta['_execution_fee']['client_amount'] = (float) ($meta['_execution_fee']['client_amount'] ?? 0);
        $meta['_execution_fee']['business_amount'] = (float) ($meta['_execution_fee']['business_amount'] ?? 0);
        $meta['_execution_fee']['charged_at'] = $meta['_execution_fee']['charged_at'] ?? null;
        $meta['_execution_fee']['transactions'] = $meta['_execution_fee']['transactions'] ?? [];
        $meta['_execution_fee']['child_id'] = (int) ($calc['business']->category_child_id ?? 0);

        return $meta;
    }

    protected function applyExecutionPlatformFees(Booking $booking): void
    {
        app(WalletFeeService::class)->applyBookingFees($booking, self::EXECUTION_FEE_CODE);
    }

    protected function resolveBusinessContext(int $businessId): array
    {
        if (! $businessId) {
            return [null, 0, 0];
        }

        $business = User::query()
            ->select(['id', 'name', 'type', 'category_id', 'category_child_id'])
            ->where('id', $businessId)
            ->where('type', 'business')
            ->first();

        if (! $business) {
            return [null, 0, 0];
        }

        return [
            $business,
            (int) ($business->category_id ?? 0),
            (int) ($business->category_child_id ?? 0),
        ];
    }

    protected function resolveBusinessServicePrice(int $businessId, int $serviceId, int $childId = 0): ?BusinessServicePrice
    {
        if ($businessId <= 0 || $serviceId <= 0) {
            return null;
        }

        // 🔥 أولوية child
        if ($childId > 0) {
            $row = BusinessServicePrice::query()
                ->where('business_id', $businessId)
                ->where('child_id', $childId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return $row;
            }
        }

        // fallback قديم
        return BusinessServicePrice::query()
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
    }

    protected function enrichCalcWithContext(array $calc, int $businessId, int $serviceId): array
    {
        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        $calc['business'] = $business;
        $calc['business_category_id'] = $categoryId;
        $calc['business_child_id'] = $childId;

        $businessPrice = $this->resolveBusinessServicePrice($businessId, $serviceId, $childId);
        if ($businessPrice) {
            $calc['business_price'] = $businessPrice;
            $calc['price'] = (float) $businessPrice->price;
        }

        $feeSnapshot = $this->resolveExecutionFeeSnapshot($businessId, $serviceId, $childId);

        $calc['service_fee_rows'] = [
            'business' => $feeSnapshot['business'],
            'client' => $feeSnapshot['client'],
        ];

        $calc['fee_snapshot'] = $feeSnapshot;

        return $calc;
    }
    


    

    protected function resolveChildServiceFeeRow(int $childId, int $serviceId): ?CategoryChildServiceFee
    {
        if ($childId <= 0 || $serviceId <= 0) {
            return null;
        }

        return CategoryChildServiceFee::query()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->first();
    }

    protected function mapChildFeeRowForSnapshot(?CategoryChildServiceFee $row, string $payer): ?array
    {
        if (! $row) {
            return null;
        }

        if ($payer === 'business') {
            if (! $row->hasBusinessFee()) {
                return null;
            }

            return [
                'id' => (int) $row->id,
                'payer' => 'business',
                'fee_type' => 'business_fee',
                'calc_type' => 'fixed',
                'amount' => (float) ($row->business_fee_amount ?? 0),
                'currency' => (string) ($row->currency ?? 'EGP'),
                'child_id' => (int) ($row->child_id ?? 0),
                'service_id' => (int) ($row->platform_service_id ?? 0),
                'is_active' => (bool) ($row->is_active ?? false),
                'sort_order' => (int) ($row->sort_order ?? 0),
                'notes' => $row->notes,
            ];
        }

        if ($payer === 'client') {
            if (! $row->hasClientFee()) {
                return null;
            }

            return [
                'id' => (int) $row->id,
                'payer' => 'client',
                'fee_type' => 'client_fee',
                'calc_type' => 'fixed',
                'amount' => (float) ($row->client_fee_amount ?? 0),
                'currency' => (string) ($row->currency ?? 'EGP'),
                'child_id' => (int) ($row->child_id ?? 0),
                'service_id' => (int) ($row->platform_service_id ?? 0),
                'is_active' => (bool) ($row->is_active ?? false),
                'sort_order' => (int) ($row->sort_order ?? 0),
                'notes' => $row->notes,
            ];
        }

        return null;
    }

    protected function resolveExecutionFeeSnapshot(int $businessId, int $serviceId, int $childId = 0): array
    {
        $row = $this->resolveChildServiceFeeRow($childId, $serviceId);

        return [
            'business_id' => $businessId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'fee_code' => self::EXECUTION_FEE_CODE,
            'business' => $this->mapChildFeeRowForSnapshot($row, 'business'),
            'client' => $this->mapChildFeeRowForSnapshot($row, 'client'),
        ];
    }




}