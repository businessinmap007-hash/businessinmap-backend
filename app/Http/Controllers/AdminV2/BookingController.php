<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\CategoryChildServiceFee;
use App\Models\CategoryServiceConfig;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingDepositService;
use App\Services\BookingReminderService;
use App\Services\ServiceEventDispatcher;
use App\Services\ServiceExecutionEngine;
use App\Models\PlatformServiceItemType;
use App\Support\AdminV2\Operations\OperationPresenter;
use App\Services\Integrations\BookingGuaranteeIntegration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private const EXECUTION_FEE_CODE = CategoryChildServiceFee::DEFAULT_FEE_CODE;

    public function __construct(
        protected BookingDepositService $bookingDepositService,
        protected ServiceExecutionEngine $serviceExecutionEngine,
        protected ServiceEventDispatcher $serviceEventDispatcher,
        protected BookingReminderService $bookingReminderService,
        protected BookingGuaranteeIntegration $bookingGuaranteeIntegration
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $sort = (string) $request->get('sort', 'starts_at');
        $dir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'starts_at', 'ends_at', 'status', 'price'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'starts_at';
        }

        $qValue = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $date = trim((string) $request->get('date', ''));

        $query = Booking::query()
            ->with([
                'user:id,name,phone,email',
                'business:id,name,phone,email,category_id,category_child_id',
                'service:id,key,name_ar,name_en,supports_deposit',
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

    public function create()
    {
        return view('admin-v2.bookings.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateBooking($request, false);

        $calc = $this->serviceExecutionEngine->prepare(
            businessId: (int) $data['business_id'],
            serviceId: (int) $data['service_id'],
            bookableId: ! empty($data['bookable_id']) ? (int) $data['bookable_id'] : null,
            quantity: (int) ($data['quantity'] ?? 1),
            pricingDate: $data['starts_at'] ?? $data['date'] ?? now()
        );

        $bookable = $calc['bookable'] ?? null;

        $data['price'] = (float) data_get($calc, 'price_breakdown.final_price', 0);
        $data = $this->applyBookableToPayload($data, $bookable);

        $data['meta'] = $this->serviceExecutionEngine->buildBookingMeta(
            existingMeta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
            calc: $calc,
            bookable: $bookable
        );

        $booking = Booking::create($data);

        $this->serviceEventDispatcher->bookingRequested(
            booking: $booking,
            actorId: auth()->id(),
            payload: [
                'source' => 'admin_v2',
                'status' => $booking->status,
                'service_id' => (int) $booking->service_id,
                'business_id' => (int) $booking->business_id,
                'client_id' => (int) $booking->user_id,
                'bookable_id' => $booking->bookable_id ? (int) $booking->bookable_id : null,
                'starts_at' => optional($booking->starts_at)->toDateTimeString(),
                'ends_at' => optional($booking->ends_at)->toDateTimeString(),
                'price' => (float) $booking->price,
            ]
        );

        $this->bookingReminderService->scheduleForBooking($booking);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم إنشاء الحجز بنجاح.');
    }

    public function show(Booking $booking, OperationPresenter $operationPresenter)
    {
        $booking = Booking::withTrashed()->findOrFail($booking->id);

        $booking->load([
            'user:id,name,code,type,phone,email',
            'business:id,name,code,type,phone,email,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit',
            'bookable',
            'latestDeposit',
            'latestDispute',
        ]);

        $deposit = $this->latestDeposit($booking);
        [$clientConfirmed, $businessConfirmed] = $this->resolveConfirmState($booking, $deposit);

        $depositPolicy = $this->depositPolicy($booking);
        $latestDispute = $booking->latestDispute;
        $operationUi = $operationPresenter->present($booking);

        return view('admin-v2.bookings.show', compact(
            'booking',
            'deposit',
            'clientConfirmed',
            'businessConfirmed',
            'depositPolicy',
            'latestDispute',
            'operationUi'
        ));
    }

    public function edit(Booking $booking)
    {
        $booking->loadMissing([
            'user:id,name,type,phone,email',
            'business:id,name,type,phone,email,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit',
            'bookable',
        ]);

        $selectedBookableId = $this->selectedBookableId($booking);

        return view('admin-v2.bookings.edit', array_merge(
            $this->formData(),
            [
                'booking' => $booking,
                'selectedBookableId' => $selectedBookableId,
                'selectedBookableItemId' => $selectedBookableId,
            ]
        ));
    }

    public function update(Request $request, Booking $booking)
    {
        $oldStatus = (string) $booking->status;

        $data = $this->validateBooking($request, true);
        $newStatus = (string) ($data['status'] ?? $oldStatus);

        /*
         * ممنوع تحويل الحجز إلى in_progress من شاشة التعديل.
         * الدخول إلى التنفيذ يجب أن يتم من زر "بدء التنفيذ" فقط.
         */
        if ($oldStatus !== Booking::STATUS_IN_PROGRESS && $newStatus === Booking::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تحويل الحجز إلى قيد التنفيذ من شاشة التعديل. استخدم زر بدء التنفيذ من صفحة عرض الحجز.',
            ]);
        }

        $serviceId = (int) ($data['service_id'] ?? $booking->service_id);
        $businessId = (int) ($data['business_id'] ?? $booking->business_id);
        $quantity = max((int) ($data['quantity'] ?? $booking->quantity ?? 1), 1);

        $bookableId = ! empty($data['bookable_id'])
            ? (int) $data['bookable_id']
            : null;

        $calc = $this->serviceExecutionEngine->prepare(
            businessId: $businessId,
            serviceId: $serviceId,
            bookableId: $bookableId,
            quantity: $quantity,
            pricingDate: $data['starts_at'] ?? $data['date'] ?? $booking->starts_at ?? now()
        );

        $bookable = $calc['bookable'] ?? null;

        $data['price'] = (float) data_get($calc, 'price_breakdown.final_price', 0);
        $data = $this->applyBookableToPayload($data, $bookable);

        $existingMeta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $incomingMeta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $mergedMeta = array_replace_recursive($existingMeta, $incomingMeta);

        $data['meta'] = $this->serviceExecutionEngine->buildBookingMeta(
            existingMeta: $mergedMeta,
            calc: $calc,
            bookable: $bookable
        );

        DB::transaction(function () use ($booking, $data) {
            $booking->fill($data);
            $booking->save();
        });

        $booking->refresh();

        $currentStatus = (string) $booking->status;

        if ($currentStatus !== $oldStatus) {
            match ($currentStatus) {
                Booking::STATUS_ACCEPTED => $this->serviceEventDispatcher->bookingAccepted(
                    booking: $booking,
                    actorId: auth()->id(),
                    payload: [
                        'source' => 'admin_v2.update',
                        'old_status' => $oldStatus,
                        'new_status' => $currentStatus,
                    ]
                ),

                Booking::STATUS_REJECTED => $this->serviceEventDispatcher->bookingRejected(
                    booking: $booking,
                    actorId: auth()->id(),
                    payload: [
                        'source' => 'admin_v2.update',
                        'old_status' => $oldStatus,
                        'new_status' => $currentStatus,
                    ]
                ),

                Booking::STATUS_CANCELLED => $this->serviceEventDispatcher->bookingCancelled(
                    booking: $booking,
                    actorId: auth()->id(),
                    payload: [
                        'source' => 'admin_v2.update',
                        'old_status' => $oldStatus,
                        'new_status' => $currentStatus,
                    ]
                ),

                Booking::STATUS_COMPLETED => $this->serviceEventDispatcher->bookingCompleted(
                    booking: $booking,
                    actorId: auth()->id(),
                    payload: [
                        'source' => 'admin_v2.update',
                        'old_status' => $oldStatus,
                        'new_status' => $currentStatus,
                    ]
                ),

                default => null,
            };
        }
        if ($currentStatus === Booking::STATUS_COMPLETED) {
            $this->bookingGuaranteeIntegration->recordCompleted($booking);
        }

        if ($currentStatus === Booking::STATUS_CANCELLED) {
            $this->bookingGuaranteeIntegration->recordCancelled($booking);
        }

        if ($currentStatus === Booking::STATUS_REJECTED) {
            $this->bookingGuaranteeIntegration->recordCancelled($booking);
        }

        if ($booking->isFinalStatus()) {
            $this->bookingReminderService->cancelForBooking($booking);
        } else {
            $this->bookingReminderService->scheduleForBooking($booking);
        }

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'تم تحديث الحجز بنجاح.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()
            ->route('admin.bookings.index')
            ->with('success', 'تم حذف الحجز.');
    }

    public function serviceLookup(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $services = PlatformService::query()
            ->when($term !== '', function ($q) use ($term) {
                $q->where('name_ar', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('key', 'like', "%{$term}%");
            })
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->limit(30)
            ->get([
                'id',
                'key',
                'name_ar',
                'name_en',
                'supports_deposit',
            ]);

        return response()->json([
            'ok' => true,
            'services' => $services,
        ]);
    }

    public function bookableItemsLookup(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId = (int) $request->get('service_id', 0);
        $term = trim((string) $request->get('q', ''));
        $selectedBookableId = (int) $request->get('selected_bookable_id', 0);

        $serviceConfig = $this->resolveServiceConfigForBusiness($businessId, $serviceId);
        $config = $serviceConfig ? $serviceConfig->configArray() : [];

        $allowedItemTypes = $this->allowedItemTypesForServiceAndConfig($serviceId, $config);
        $requiresBookableItem = $this->requiresBookableItemFromConfig($config);

        $query = BookableItem::query()
            ->where('is_active', 1);

        if ($businessId > 0) {
            $query->where('business_id', $businessId);
        }

        if ($serviceId > 0) {
            $query->where('service_id', $serviceId);
        }

        if (! empty($allowedItemTypes)) {
            $query->whereIn('item_type', $allowedItemTypes);
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

        if ($selectedBookableId > 0 && ! $rows->contains('id', $selectedBookableId)) {
            $selectedItem = BookableItem::query()
                ->where('id', $selectedBookableId)
                ->first([
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

            if ($selectedItem) {
                $rows->prepend($selectedItem);
            }
        }

        return response()->json([
            'ok' => true,
            'service_config' => [
                'exists' => (bool) $serviceConfig,
                'id' => $serviceConfig ? (int) $serviceConfig->id : null,
                'requires_bookable_item' => $requiresBookableItem,
                'allowed_item_types' => $allowedItemTypes,
                'booking_modes' => data_get($config, 'booking_modes', []),
                'item_family' => data_get($config, 'item_family'),
                'requires_start_end' => filter_var(data_get($config, 'requires_start_end', false), FILTER_VALIDATE_BOOLEAN),
                'supports_quantity' => filter_var(data_get($config, 'supports_quantity', true), FILTER_VALIDATE_BOOLEAN),
                'supports_guest_count' => filter_var(data_get($config, 'supports_guest_count', false), FILTER_VALIDATE_BOOLEAN),
                'supports_extras' => filter_var(data_get($config, 'supports_extras', false), FILTER_VALIDATE_BOOLEAN),
                'required_fields' => data_get($config, 'required_fields', []),
            ],
            'items' => $rows->map(function (BookableItem $item) {
                return [
                    'id' => (int) $item->id,
                    'business_id' => (int) $item->business_id,
                    'service_id' => (int) $item->service_id,
                    'item_type' => (string) ($item->item_type ?? ''),
                    'title' => (string) ($item->title ?? ''),
                    'code' => (string) ($item->code ?? ''),
                    // Units are inventory only; price/deposit come from
                    // business_service_prices (resolved by the pricing preview).
                    'price' => 0.0,
                    'capacity' => $item->capacity !== null ? (int) $item->capacity : null,
                    'quantity' => $item->quantity !== null ? (int) $item->quantity : null,
                    'deposit_enabled' => false,
                    'deposit_percent' => 0,
                ];
            })->values(),
        ]);
    }

    public function pricingPreview(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $serviceId = (int) $request->get('service_id', 0);
        $bookableId = (int) $request->get('bookable_id', 0);
        $quantity = max((int) $request->get('quantity', 1), 1);

        $startsAt = $request->get('starts_at');
        $endsAt = $request->get('ends_at');

        if ($businessId <= 0 || $serviceId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'business_id و service_id مطلوبان',
            ], 422);
        }

        $serviceConfig = $this->resolveServiceConfigForBusiness($businessId, $serviceId);
        $config = $serviceConfig ? $serviceConfig->configArray() : [];

        $requiresBookableItem = $this->requiresBookableItemFromConfig($config);
        $allowedItemTypes = $this->allowedItemTypesForServiceAndConfig($serviceId, $config);

        if ($requiresBookableItem && $bookableId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'هذه الخدمة تتطلب اختيار عنصر قابل للحجز مثل غرفة أو وحدة.',
                'code' => 'bookable_required',
                'service_config' => $this->serviceConfigPayload($serviceConfig),
            ], 422);
        }

        if ($bookableId > 0 && ! empty($allowedItemTypes)) {
            $bookableType = BookableItem::query()
                ->where('id', $bookableId)
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->value('item_type');

            if (! $bookableType || ! in_array((string) $bookableType, $allowedItemTypes, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'نوع العنصر المختار غير مسموح لهذه الخدمة.',
                    'code' => 'bookable_type_not_allowed',
                    'allowed_item_types' => $allowedItemTypes,
                ], 422);
            }
        }

        try {
            $calc = $this->serviceExecutionEngine->preview(
                businessId: $businessId,
                serviceId: $serviceId,
                bookableId: $bookableId > 0 ? $bookableId : null,
                quantity: $quantity,
                startsAt: $startsAt,
                endsAt: $endsAt
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'تعذر حساب التسعير أو التوفر.',
            ], 500);
        }

        $availability = $calc['availability'] ?? null;

        if ($availability && ! ($availability['available'] ?? false)) {
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

        $service = $calc['service'];
        $businessPrice = $calc['business_price'];
        $bookable = $calc['bookable'] ?? null;

        return response()->json([
            'ok' => true,
            'service_config' => $this->serviceConfigPayload($serviceConfig),
            'service' => [
                'id' => (int) $service->id,
                'key' => (string) ($service->key ?? ''),
                'name_ar' => (string) ($service->name_ar ?? ''),
                'name_en' => (string) ($service->name_en ?? ''),
                'supports_deposit' => (bool) ($service->supports_deposit ?? false),
            ],
            'business_price' => [
                'id' => (int) ($businessPrice->id ?? 0),
                'price' => (float) ($businessPrice->price ?? 0),
                'currency' => (string) ($businessPrice->currency ?? 'EGP'),
                'discount_enabled' => (bool) ($businessPrice->discount_enabled ?? false),
                'discount_percent' => (int) ($businessPrice->discount_percent ?? 0),
                // Deposit is single-source from the resolved policy (Phase 4);
                // business_service_prices no longer carries deposit config.
                'deposit_enabled' => (bool) ($calc['deposit_policy']['enabled'] ?? false),
                'deposit_percent' => (int) round((float) ($calc['deposit_policy']['configured_percent'] ?? 0)),
                'child_id' => (int) ($businessPrice->child_id ?? 0),
            ],
            'bookable' => $bookable ? [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'code' => (string) ($bookable->code ?? ''),
                'item_type' => (string) ($bookable->item_type ?? ''),
                // Inventory only; real price/deposit are in the pricing/deposit
                // payload below (resolved from business_service_prices).
                'price' => 0.0,
                'deposit_enabled' => false,
                'deposit_percent' => 0,
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
            'pricing' => $calc['price_breakdown'] ?? [],
            'deposit_policy' => $calc['deposit_policy'] ?? [],
        ]);
    }

    public function startConfirmClient(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->client_confirmed = true;
                $deposit->save();
            }

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['client'] = 1;
            $meta['_start_confirm']['client_at'] = now()->toDateTimeString();

            $booking->meta = $meta;
            $booking->save();
        });

        $booking->refresh();

        $this->serviceEventDispatcher->bookingClientConfirmed(
            booking: $booking,
            actorId: auth()->id(),
            payload: [
                'source' => 'admin_v2.start_confirm_client',
            ]
        );

        return back()->with('success', 'تم تأكيد العميل.');
    }

    public function startConfirmBusiness(Booking $booking)
    {
        $deposit = $this->latestDeposit($booking);

        DB::transaction(function () use ($booking, $deposit) {
            if ($deposit) {
                $deposit->business_confirmed = true;
                $deposit->save();
            }

            $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
            $meta['_start_confirm'] = $meta['_start_confirm'] ?? [];
            $meta['_start_confirm']['business'] = 1;
            $meta['_start_confirm']['business_at'] = now()->toDateTimeString();

            $booking->meta = $meta;
            $booking->save();
        });

        $booking->refresh();

        $this->serviceEventDispatcher->bookingBusinessConfirmed(
            booking: $booking,
            actorId: auth()->id(),
            payload: [
                'source' => 'admin_v2.start_confirm_business',
            ]
        );

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

    public function depositFreeze(Booking $booking)
    {
        $depositPolicy = $this->depositPolicy($booking);

        if (! ($depositPolicy['required'] ?? false)) {
            return back()->with('error', 'Deposit غير مفعل لهذا الحجز.');
        }

        try {
            $this->bookingDepositService->freezeForBooking(
                $booking,
                (float) ($depositPolicy['hold'] ?? $depositPolicy['wallet_hold_amount'] ?? $depositPolicy['amount'] ?? 0),
                $depositPolicy
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

    public function start(Booking $booking)
    {
        try {
            $this->serviceExecutionEngine->moveBookingToInProgress($booking);
            $booking->refresh();

            $this->serviceEventDispatcher->bookingStarted(
                booking: $booking,
                actorId: auth()->id(),
                payload: [
                    'source' => 'admin_v2.start',
                    'status' => $booking->status,
                ]
            );

            return redirect()
                ->route('admin.bookings.show', $booking)
                ->with('success', 'تم بدء تنفيذ الحجز وخصم رسوم التنفيذ بنجاح.');
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.bookings.show', $booking)
                ->withErrors($e->errors())
                ->with('error', 'لا يمكن بدء التنفيذ قبل استكمال المتطلبات.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.bookings.show', $booking)
                ->with('error', 'تعذر بدء التنفيذ: ' . $e->getMessage());
        }
    }

    public function complete(Booking $booking)
    {
        try {
            if ((string) $booking->status !== Booking::STATUS_IN_PROGRESS) {
                return redirect()
                    ->route('admin.bookings.show', $booking)
                    ->with('error', 'لا يمكن إنهاء الحجز إلا إذا كان قيد التنفيذ.');
            }

            $booking->status = Booking::STATUS_COMPLETED;
            $booking->save();

            $booking->refresh();

            $this->serviceEventDispatcher->bookingCompleted(
                booking: $booking,
                actorId: auth()->id(),
                payload: [
                    'source' => 'admin_v2.complete',
                    'status' => $booking->status,
                ]
            );

            $this->bookingReminderService->cancelForBooking($booking);

            return redirect()
                ->route('admin.bookings.show', $booking)
                ->with('success', 'تم إنهاء الحجز بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.bookings.show', $booking)
                ->with('error', 'تعذر إنهاء الحجز: ' . $e->getMessage());
        }
    }

    public function cancel(Booking $booking)
    {
        try {
            if (in_array((string) $booking->status, [
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_REJECTED,
            ], true)) {
                return redirect()
                    ->route('admin.bookings.show', $booking)
                    ->with('error', 'لا يمكن إلغاء حجز في حالة نهائية.');
            }

            $booking->status = Booking::STATUS_CANCELLED;
            $booking->save();

            $this->serviceEventDispatcher->bookingCancelled(
                booking: $booking,
                actorId: auth()->id(),
                payload: [
                    'source' => 'admin_v2.cancel',
                    'status' => $booking->status,
                ]
            );

            $this->bookingReminderService->cancelForBooking($booking);

            return redirect()
                ->route('admin.bookings.show', $booking)
                ->with('success', 'تم إلغاء الحجز بنجاح.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.bookings.show', $booking)
                ->with('error', 'تعذر إلغاء الحجز: ' . $e->getMessage());
        }
    }

    private function formData(): array
    {
        $services = PlatformService::query()
        ->select([
            'id',
            'key',
            'name_ar',
            'name_en',
            'is_active',
            'supports_deposit',
        ])
        ->where('is_active', 1)
        ->orderBy('name_ar')
        ->get();

        $clients = User::query()
            ->select([
                'id',
                'name',
                'type',
                'phone',
                'email',
            ])
            ->orderBy('name')
            ->limit(500)
            ->get();

        $businesses = User::query()
            ->select([
                'id',
                'name',
                'type',
                'phone',
                'email',
                'category_id',
                'category_child_id',
            ])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $businessServicePrices = BusinessServicePrice::query()
            ->select([
                'id',
                'business_id',
                'service_id',
                'child_id',
                'price',
                'currency',
                'is_active',
                'discount_enabled',
                'discount_percent',
                'deposit_enabled',
                'deposit_percent',
            ])
            ->where('is_active', 1)
            ->get();

        $statusOptions = Booking::statusOptions();

        return compact(
            'services',
            'clients',
            'businesses',
            'businessServicePrices',
            'statusOptions'
        );
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

        if (empty($data['timezone'])) {
            $data['timezone'] = 'Africa/Cairo';
        }

        $data['meta'] = is_array($request->input('meta')) ? $request->input('meta') : [];

        $durationUnit = (string) ($data['duration_unit'] ?? 'day');
        $durationValue = max((int) ($data['duration_value'] ?? $data['quantity'] ?? 1), 1);

        $data['quantity'] = max((int) ($data['quantity'] ?? $durationValue), 1);

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
                    $data['duration_value'] = max(1, $start->diffInDays($end));
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

            if (in_array($durationUnit, ['day', 'hour', 'minute', 'week', 'month', 'year'], true)) {
                $data['quantity'] = max((int) $data['duration_value'], 1);
            }
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
        $serviceId = (int) ($data['service_id'] ?? 0);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
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

        $serviceConfig = $this->resolveServiceConfigForBusiness($businessId, $serviceId);
        $config = $serviceConfig ? $serviceConfig->configArray() : [];

        $requiresBookableItem = $this->requiresBookableItemFromConfig($config);
        $allowedItemTypes = $this->allowedItemTypesForServiceAndConfig($serviceId, $config);

        if ($requiresBookableItem && empty($data['bookable_id'])) {
            throw ValidationException::withMessages([
                'bookable_id' => 'هذه الخدمة تتطلب اختيار عنصر قابل للحجز مثل غرفة أو وحدة.',
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

            if (! empty($allowedItemTypes) && ! in_array((string) $bookable->item_type, $allowedItemTypes, true)) {
                throw ValidationException::withMessages([
                    'bookable_id' => 'نوع العنصر القابل للحجز غير مسموح لهذه الخدمة.',
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
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $sc = is_array($meta['_start_confirm'] ?? null) ? $meta['_start_confirm'] : [];

        $metaClientConfirmed = ! empty($sc['client']);
        $metaBusinessConfirmed = ! empty($sc['business']);

        if ($deposit) {
            return [
                ((bool) $deposit->client_confirmed) || $metaClientConfirmed,
                ((bool) $deposit->business_confirmed) || $metaBusinessConfirmed,
            ];
        }

        return [
            $metaClientConfirmed,
            $metaBusinessConfirmed,
        ];
    }

    protected function depositPolicy(Booking $booking): array
    {
        return $this->serviceExecutionEngine->depositPolicy($booking);
    }

    protected function selectedBookableId(Booking $booking): int
    {
        return (int) (
            old('bookable_id')
            ?? $booking->bookable_id
            ?? data_get($booking->meta, 'bookable.id')
            ?? data_get($booking->meta, 'bookable_item.id')
            ?? data_get($booking->meta, 'booking_test_form.bookable_id')
            ?? data_get($booking->meta, 'booking_test_form.bookable_item_id')
            ?? 0
        );
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

    protected function resolveBusinessContext(int $businessId): array
    {
        if ($businessId <= 0) {
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

        return BusinessServicePrice::query()
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
    }

    protected function resolveServiceConfigForBusiness(int $businessId, int $serviceId): ?CategoryServiceConfig
    {
        if ($businessId <= 0 || $serviceId <= 0) {
            return null;
        }

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            return null;
        }

        if ($categoryId > 0 && $childId > 0) {
            $config = CategoryServiceConfig::query()
                ->active(1)
                ->forCategory($categoryId)
                ->forChild($childId)
                ->forService($serviceId)
                ->ordered()
                ->first();

            if ($config) {
                return $config;
            }
        }

        if ($childId > 0) {
            $config = CategoryServiceConfig::query()
                ->active(1)
                ->forChild($childId)
                ->forService($serviceId)
                ->ordered()
                ->first();

            if ($config) {
                return $config;
            }
        }

        if ($categoryId > 0) {
            return CategoryServiceConfig::query()
                ->active(1)
                ->forCategory($categoryId)
                ->forService($serviceId)
                ->ordered()
                ->first();
        }

        return null;
    }

    protected function serviceConfigPayload(?CategoryServiceConfig $serviceConfig): array
    {
        $config = $serviceConfig ? $serviceConfig->configArray() : [];

        return [
            'exists' => (bool) $serviceConfig,
            'id' => $serviceConfig ? (int) $serviceConfig->id : null,
            'requires_bookable_item' => $this->requiresBookableItemFromConfig($config),
            'allowed_item_types' => $this->allowedItemTypesForServiceAndConfig(
                (int) ($serviceConfig->platform_service_id ?? $serviceConfig->service_id ?? 0),
                $config
            ),
            'booking_modes' => data_get($config, 'booking_modes', []),
            'item_family' => data_get($config, 'item_family'),
            'requires_start_end' => filter_var(data_get($config, 'requires_start_end', false), FILTER_VALIDATE_BOOLEAN),
            'supports_quantity' => filter_var(data_get($config, 'supports_quantity', true), FILTER_VALIDATE_BOOLEAN),
            'supports_guest_count' => filter_var(data_get($config, 'supports_guest_count', false), FILTER_VALIDATE_BOOLEAN),
            'supports_extras' => filter_var(data_get($config, 'supports_extras', false), FILTER_VALIDATE_BOOLEAN),
            'required_fields' => data_get($config, 'required_fields', []),
        ];
    }

    protected function allowedItemTypesForServiceAndConfig(int $serviceId, array $config = []): array
    {
        if ($serviceId <= 0) {
            return [];
        }

        $baseTypes = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->pluck('key')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($baseTypes === []) {
            return [];
        }

        $restrictedTypes = $this->allowedItemTypesFromConfig($config);

        if ($restrictedTypes === []) {
            return $baseTypes;
        }

        return collect($baseTypes)
            ->filter(fn ($type) => in_array($type, $restrictedTypes, true))
            ->values()
            ->all();
    }

    protected function allowedItemTypesFromConfig(array $config): array
    {
        $types = data_get($config, 'allowed_item_types', []);

        if (! is_array($types)) {
            return [];
        }

        return collect($types)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function requiresBookableItemFromConfig(array $config): bool
    {
        return filter_var(data_get($config, 'requires_bookable_item', false), FILTER_VALIDATE_BOOLEAN);
    }
}