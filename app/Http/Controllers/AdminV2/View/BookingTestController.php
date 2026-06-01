<?php

namespace App\Http\Controllers\AdminV2\view;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\PlatformService;
use App\Models\PlatformServiceFeePromotion;
use App\Services\PlatformServiceFeePromotionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingTestController extends Controller
{
    public function index()
    {
        $bookingService = $this->bookingService();

        $roots = Category::query()
            ->where(function ($q) {
                if (Schema::hasColumn('categories', 'parent_id')) {
                    $q->where('parent_id', 0)->orWhereNull('parent_id');
                }
            })
            ->when(Schema::hasColumn('categories', 'is_active'), function ($q) {
                $q->where('is_active', 1);
            })
            ->orderBy(Schema::hasColumn('categories', 'reorder') ? 'reorder' : 'id')
            ->get();

        return view('admin-v2.booking-test.client', [
            'roots' => $roots,
            'bookingService' => $bookingService,
        ]);
    }

    public function children(Request $request): JsonResponse
    {
        $rootId = (int) $request->get('root_id');

        if ($rootId <= 0) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        $children = collect();

        if (Schema::hasTable('category_parent_child')) {
            $childIds = DB::table('category_parent_child')
                ->where('parent_id', $rootId)
                ->pluck('child_id')
                ->filter()
                ->values();

            $children = CategoryChild::query()
                ->whereIn('id', $childIds)
                ->when(Schema::hasColumn('category_children_master', 'is_active'), function ($q) {
                    $q->where('is_active', 1);
                })
                ->orderBy(Schema::hasColumn('category_children_master', 'reorder') ? 'reorder' : 'id')
                ->get();
        } elseif (Schema::hasTable('category_children_master')) {
            $children = CategoryChild::query()
                ->when(Schema::hasColumn('category_children_master', 'parent_id'), function ($q) use ($rootId) {
                    $q->where('parent_id', $rootId);
                })
                ->when(Schema::hasColumn('category_children_master', 'is_active'), function ($q) {
                    $q->where('is_active', 1);
                })
                ->orderBy(Schema::hasColumn('category_children_master', 'reorder') ? 'reorder' : 'id')
                ->get();
        }

        return response()->json([
            'ok' => true,
            'items' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $this->displayName($child),
            ])->values(),
        ]);
    }

    public function businesses(Request $request): JsonResponse
    {
        $childId = (int) $request->get('child_id');
        $serviceId = (int) $request->get('service_id');

        if ($serviceId <= 0) {
            $serviceId = (int) optional($this->bookingService())->id;
        }

        if ($childId <= 0 || $serviceId <= 0) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        $businessIds = collect();

        if (Schema::hasTable('business_service_prices')) {
            $q = DB::table('business_service_prices')
                ->where('service_id', $serviceId);

            if (Schema::hasColumn('business_service_prices', 'child_id')) {
                $q->where('child_id', $childId);
            } elseif (Schema::hasColumn('business_service_prices', 'category_child_id')) {
                $q->where('category_child_id', $childId);
            }

            if (Schema::hasColumn('business_service_prices', 'is_active')) {
                $q->where('is_active', 1);
            }

            $businessColumn = Schema::hasColumn('business_service_prices', 'business_id')
                ? 'business_id'
                : (Schema::hasColumn('business_service_prices', 'user_id') ? 'user_id' : null);

            if ($businessColumn) {
                $businessIds = $q->pluck($businessColumn)->filter()->unique()->values();
            }
        }

        if ($businessIds->isEmpty() && Schema::hasTable('user_platform_service')) {
            $q = DB::table('user_platform_service')
                ->where('service_id', $serviceId);

            if (Schema::hasColumn('user_platform_service', 'is_active')) {
                $q->where('is_active', 1);
            }

            $businessColumn = Schema::hasColumn('user_platform_service', 'user_id')
                ? 'user_id'
                : (Schema::hasColumn('user_platform_service', 'business_id') ? 'business_id' : null);

            if ($businessColumn) {
                $businessIds = $q->pluck($businessColumn)->filter()->unique()->values();
            }
        }

        $businesses = User::query()
            ->whereIn('id', $businessIds)
            ->when(Schema::hasColumn('users', 'is_active'), function ($q) {
                $q->where('is_active', 1);
            })
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'ok' => true,
            'items' => $businesses->map(fn ($business) => [
                'id' => $business->id,
                'name' => $business->name ?? $business->full_name ?? $business->business_name ?? ('Business #' . $business->id),
                'email' => $business->email ?? '',
                'phone' => $business->phone ?? '',
            ])->values(),
        ]);
    }

    public function bookableItems(Request $request): JsonResponse
    {
        $businessId = (int) $request->get('business_id');
        $serviceId = (int) $request->get('service_id');
        $childId = (int) $request->get('child_id');

        if ($businessId <= 0 || $serviceId <= 0) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        if (!class_exists(BookableItem::class) || !Schema::hasTable('bookable_items')) {
            return response()->json([
                'ok' => true,
                'items' => [],
            ]);
        }

        $q = BookableItem::query();

        if (Schema::hasColumn('bookable_items', 'business_id')) {
            $q->where('business_id', $businessId);
        } elseif (Schema::hasColumn('bookable_items', 'user_id')) {
            $q->where('user_id', $businessId);
        }

        if (Schema::hasColumn('bookable_items', 'service_id')) {
            $q->where('service_id', $serviceId);
        }

        if ($childId > 0) {
            if (Schema::hasColumn('bookable_items', 'child_id')) {
                $q->where('child_id', $childId);
            } elseif (Schema::hasColumn('bookable_items', 'category_child_id')) {
                $q->where('category_child_id', $childId);
            }
        }

        if (Schema::hasColumn('bookable_items', 'is_active')) {
            $q->where('is_active', 1);
        }

        $items = $q->orderBy('id', 'desc')->limit(50)->get();

        return response()->json([
            'ok' => true,
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name_ar
                    ?? $item->name_en
                    ?? $item->title
                    ?? $item->name
                    ?? ('Item #' . $item->id),
                'type' => $item->type ?? $item->item_type ?? '',
                'capacity' => $item->capacity ?? null,
                'price' => $this->numberValue($item->price ?? $item->base_price ?? 0),
            ])->values(),
        ]);
    }

    public function pricingPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'root_id' => ['nullable', 'integer'],
            'child_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'business_id' => ['required', 'integer'],
            'bookable_item_id' => ['nullable', 'integer'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
        ]);

        $preview = $this->buildPricingPreview($data);

        return response()->json([
            'ok' => true,
            'preview' => $preview,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'root_id' => ['nullable', 'integer'],
            'child_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'business_id' => ['required', 'integer'],
            'bookable_item_id' => ['nullable', 'integer'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!Schema::hasTable('bookings')) {
            return response()->json([
                'ok' => false,
                'message' => 'جدول bookings غير موجود.',
            ], 422);
        }

        $preview = $this->buildPricingPreview($data);

        $booking = new Booking();
          $startDateTime = !empty($data['start_at'])
            ? \Carbon\Carbon::parse($data['start_at'])
            : now();

        $endDateTime = !empty($data['end_at'])
            ? \Carbon\Carbon::parse($data['end_at'])
            : null;

        $startDate = $startDateTime->toDateString();
        $startTime = $startDateTime->format('H:i:s');

        $endDate = $endDateTime ? $endDateTime->toDateString() : null;
        $endTime = $endDateTime ? $endDateTime->format('H:i:s') : null;

        $clientId = Auth::id();

        $itemId = $data['bookable_item_id'] ?? null;
        $itemType = null;

        if (!empty($itemId) && Schema::hasTable('bookable_items')) {
            $itemRow = BookableItem::query()->find($itemId);

            if ($itemRow) {
                $itemType = $itemRow->type
                    ?? $itemRow->item_type
                    ?? $itemRow->bookable_item_type
                    ?? null;
            }
        }
        

        $payload = [
    'status' => 'pending',

    /*
    |--------------------------------------------------------------------------
    | Legacy / current booking date fields
    |--------------------------------------------------------------------------
    */
    'date' => $startDate,
    'time' => $startTime,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'start_time' => $startTime,
    'end_time' => $endTime,

    /*
    |--------------------------------------------------------------------------
    | Newer datetime fields
    |--------------------------------------------------------------------------
    */
    'start_at' => $data['start_at'] ?? null,
    'starts_at' => $data['start_at'] ?? null,
    'end_at' => $data['end_at'] ?? null,
    'ends_at' => $data['end_at'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | Parties
    |--------------------------------------------------------------------------
    */
    'client_id' => $clientId,
    'user_id' => $clientId,
    'customer_id' => $clientId,

    'business_id' => $data['business_id'],
    'provider_id' => $data['business_id'],
    'owner_id' => $data['business_id'],

    /*
    |--------------------------------------------------------------------------
    | Service / category
    |--------------------------------------------------------------------------
    */
    'service_id' => $data['service_id'],
    'platform_service_id' => $data['service_id'],

    'category_id' => $data['root_id'] ?? null,
    'root_id' => $data['root_id'] ?? null,

    'child_id' => $data['child_id'],
    'category_child_id' => $data['child_id'],

    /*
    |--------------------------------------------------------------------------
    | Bookable item
    |--------------------------------------------------------------------------
    */
    'bookable_item_id' => $itemId,
    'item_id' => $itemId,
    'room_id' => $itemId,

    'bookable_item_type' => $itemType,
    'item_type' => $itemType,

    /*
    |--------------------------------------------------------------------------
    | Quantity / guests
    |--------------------------------------------------------------------------
    */
    'quantity' => $data['quantity'] ?? 1,
    'guest_count' => $data['guest_count'] ?? null,
    'guests' => $data['guest_count'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | Prices
    |--------------------------------------------------------------------------
    */
    'price' => $preview['base_price'],
    'base_price' => $preview['base_price'],
    'subtotal' => $preview['base_price'],

    'platform_fee' => $preview['platform_fee'],
    'client_platform_fee' => $preview['client_platform_fee'] ?? $preview['platform_fee'],
    'business_platform_fee' => $preview['business_platform_fee'] ?? 0,

    'total_price' => $preview['total'],
    'total' => $preview['total'],
    'amount' => $preview['total'],

    'deposit_amount' => $preview['deposit_amount'],

    /*
    |--------------------------------------------------------------------------
    | Notes / meta
    |--------------------------------------------------------------------------
    */
    'notes' => $data['notes'] ?? null,
    'note' => $data['notes'] ?? null,

    'meta' => [
        'source' => 'admin_v2_booking_test_client_page',
        'pricing' => $preview,
        'booking_test_form' => [
            'root_id' => $data['root_id'] ?? null,
            'child_id' => $data['child_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'business_id' => $data['business_id'] ?? null,
            'bookable_item_id' => $itemId,
            'bookable_item_type' => $itemType,
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'guest_count' => $data['guest_count'] ?? null,
            'notes' => $data['notes'] ?? null,
        ],
        'test_context' => [
            'created_by_admin_id' => Auth::id(),
            'created_from' => 'BookingTestController',
        ],
    ],
];

        foreach ($payload as $column => $value) {
            if (Schema::hasColumn('bookings', $column)) {
                $booking->{$column} = $value;
            }
        }

        if (Schema::hasColumn('bookings', 'meta')) {
            $booking->meta = $payload['meta'];
        }

        $booking->save();

        return response()->json([
            'ok' => true,
            'message' => 'تم إنشاء حجز اختباري بنجاح.',
            'booking_id' => $booking->id,
            'status' => $booking->status ?? 'pending',
            'preview' => $preview,
        ]);
    }

    private function bookingService(): ?PlatformService
    {
        if (!Schema::hasTable('platform_services')) {
            return null;
        }

        return PlatformService::query()
            ->where(function ($q) {
                if (Schema::hasColumn('platform_services', 'key')) {
                    $q->where('key', 'booking');
                }

                if (Schema::hasColumn('platform_services', 'code')) {
                    $q->orWhere('code', 'booking');
                }

                if (Schema::hasColumn('platform_services', 'slug')) {
                    $q->orWhere('slug', 'booking');
                }
            })
            ->first();
    }

    private function buildPricingPreview(array $data): array
    {
        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        $service = PlatformService::query()->find($data['service_id']);
        $business = User::query()->find($data['business_id']);
        $item = null;

        if (!empty($data['bookable_item_id']) && Schema::hasTable('bookable_items')) {
            $item = BookableItem::query()->find($data['bookable_item_id']);
        }

        $unitPrice = $this->resolveBusinessPrice(
            (int) $data['business_id'],
            (int) $data['service_id'],
            (int) $data['child_id'],
            $item
        );

        /*
        |--------------------------------------------------------------------------
        | Duration based pricing for booking test
        |--------------------------------------------------------------------------
        | في صفحة تجربة الحجز، السعر يعتمد على مدة الحجز إذا تم اختيار بداية ونهاية.
        | مثال: غرفة سعرها 600 في اليوم × مدة 8 أيام = 4800.
        | لا نستخدم quantity كمضاعف أساسي هنا حتى لا يختلط مع مدة الحجز.
        |--------------------------------------------------------------------------
        */
        $duration = $this->calculateBookingDuration(
            $data['start_at'] ?? null,
            $data['end_at'] ?? null
        );

        $billableUnits = $duration['billable_days'];

        if ($billableUnits <= 0) {
            $billableUnits = 1;
        }

        $basePrice = $unitPrice * $billableUnits;

        /*
        |--------------------------------------------------------------------------
        | Temporary test-page fee source
        |--------------------------------------------------------------------------
        | إلى أن يتم ربط BookingEngine رسميًا بـ CategoryChildServiceFee،
        | سنستخدم رسوم PlatformService الحالية كرسوم افتراضية.
        | لاحقًا سيتم استبدال هذا الجزء بقراءة:
        | CategoryChildServiceFee business/client fees
        |--------------------------------------------------------------------------
        */
        $defaultClientFee = $this->calculatePlatformFee($service, $basePrice);
        $defaultBusinessFee = 0.0;

        $feePromotionResult = app(PlatformServiceFeePromotionService::class)->apply(
            (int) $data['service_id'],
            (int) $data['child_id'],
            $defaultBusinessFee,
            $defaultClientFee
        );

        $clientPlatformFee = (float) $feePromotionResult['final_client_fee'];
        $businessPlatformFee = (float) $feePromotionResult['final_business_fee'];

        $total = $basePrice + $clientPlatformFee;

        $depositAmount = $this->calculateDeposit($business, $service, $total);

        return [
            'currency' => 'EGP',
            'quantity' => $quantity,

                'duration' => [
                    'total_minutes' => $duration['total_minutes'],
                    'days' => $duration['days'],
                    'hours' => $duration['hours'],
                    'minutes' => $duration['minutes'],
                    'billable_days' => $duration['billable_days'],
                    'label' => $duration['label'],
                ],

                'billable_units' => $billableUnits,
                'billing_note' => 'تم حساب السعر حسب مدة الحجز وليس حسب خانة العدد / الكمية.',
            

            'unit_price' => round($unitPrice, 2),
            'base_price' => round($basePrice, 2),

            'platform_fee' => round($clientPlatformFee, 2),
            'client_platform_fee' => round($clientPlatformFee, 2),
            'business_platform_fee' => round($businessPlatformFee, 2),

            'total' => round($total, 2),

            'deposit_amount' => round($depositAmount, 2),
            'deposit_note' => $depositAmount > 0
                ? 'العربون / الضمان مستقل ولا يخصم من سعر الخدمة.'
                : 'لا يوجد عربون مطلوب لهذا الحجز.',

            'fees' => [
                'original_business_fee' => $feePromotionResult['original_business_fee'],
                'original_client_fee' => $feePromotionResult['original_client_fee'],

                'final_business_fee' => $feePromotionResult['final_business_fee'],
                'final_client_fee' => $feePromotionResult['final_client_fee'],

                'platform_promotion_applied' => $feePromotionResult['platform_promotion_applied'],
                'platform_promotion' => $feePromotionResult['platform_promotion'],

                'platform_discount_business_fee' => $feePromotionResult['platform_discount_business_fee'],
                'platform_discount_client_fee' => $feePromotionResult['platform_discount_client_fee'],
                'platform_discount_total' => $feePromotionResult['platform_discount_total'],
            ],

            'service' => [
                'id' => optional($service)->id,
                'name' => $this->displayName($service),
                'fee_type' => optional($service)->fee_type,
                'fee_value' => $this->numberValue(optional($service)->fee_value),
            ],

            'business' => [
                'id' => optional($business)->id,
                'name' => optional($business)->name
                    ?? optional($business)->business_name
                    ?? ('Business #' . ($data['business_id'] ?? '')),
            ],

            'bookable_item' => $item ? [
                'id' => $item->id,
                'name' => $item->name_ar
                    ?? $item->name_en
                    ?? $item->title
                    ?? $item->name
                    ?? ('Item #' . $item->id),
            ] : null,
        ];
    }

    private function resolveBusinessPrice(int $businessId, int $serviceId, int $childId, ?BookableItem $item = null): float
    {
        if ($item) {
            $itemPrice = $this->numberValue($item->price ?? $item->base_price ?? null);

            if ($itemPrice > 0) {
                return $itemPrice;
            }
        }

        if (!Schema::hasTable('business_service_prices')) {
            return 0;
        }

        $q = BusinessServicePrice::query();

        if (Schema::hasColumn('business_service_prices', 'business_id')) {
            $q->where('business_id', $businessId);
        } elseif (Schema::hasColumn('business_service_prices', 'user_id')) {
            $q->where('user_id', $businessId);
        }

        if (Schema::hasColumn('business_service_prices', 'service_id')) {
            $q->where('service_id', $serviceId);
        }

        if (Schema::hasColumn('business_service_prices', 'child_id')) {
            $q->where('child_id', $childId);
        } elseif (Schema::hasColumn('business_service_prices', 'category_child_id')) {
            $q->where('category_child_id', $childId);
        }

        if (Schema::hasColumn('business_service_prices', 'is_active')) {
            $q->where('is_active', 1);
        }

        $priceRow = $q->first();

        if (!$priceRow) {
            return 0;
        }

        return $this->numberValue(
            $priceRow->price
            ?? $priceRow->base_price
            ?? $priceRow->amount
            ?? 0
        );
    }

    private function calculatePlatformFee(?PlatformService $service, float $basePrice): float
    {
        if (!$service) {
            return 0;
        }

        $serviceKey = strtolower(trim((string) (
            $service->key
            ?? $service->code
            ?? $service->slug
            ?? ''
        )));

        $feeType = strtolower(trim((string) ($service->fee_type ?? 'fixed')));
        $feeValue = $this->numberValue($service->fee_value ?? 0);

        if ($feeValue <= 0) {
            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Booking fee as one-time usage fee
        |--------------------------------------------------------------------------
        | خدمة booking رسومها على استخدام الخدمة مرة واحدة فقط.
        |--------------------------------------------------------------------------
        */
        if ($serviceKey === 'booking') {
            return $feeValue;
        }

        if (in_array($feeType, ['percent', 'percentage', '%'], true)) {
            return ($basePrice * $feeValue) / 100;
        }

        return $feeValue;
    }

    private function calculateDeposit(?User $business, ?PlatformService $service, float $total): float
    {
        if (!$business) {
            return 0;
        }

        $businessHoldEnabled = (bool) ($business->booking_hold_enabled ?? false);
        $businessHoldAmount = $this->numberValue($business->booking_hold_amount ?? 0);

        if ($businessHoldEnabled && $businessHoldAmount > 0) {
            return $businessHoldAmount;
        }

        $supportsDeposit = (bool) ($service->supports_deposit ?? false);
        $maxPercent = $this->numberValue($service->max_deposit_percent ?? 0);

        if ($supportsDeposit && $maxPercent > 0 && $total > 0) {
            return ($total * $maxPercent) / 100;
        }

        return 0;
    }

    private function numberValue($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) $value;
    }

    private function displayName($model): string
    {
        if (!$model) {
            return '';
        }

        return (string) (
            $model->name_ar
            ?? $model->name_en
            ?? $model->name
            ?? $model->title
            ?? $model->key
            ?? $model->code
            ?? ('#' . ($model->id ?? Str::random(4)))
        );
    }
private function calculateBookingDuration(?string $startAt, ?string $endAt): array
{
    /*
    |--------------------------------------------------------------------------
    | Temporary hotel-like billing policy for booking test
    |--------------------------------------------------------------------------
    | grace_hours:
    | عدد ساعات سماح بعد اليوم الكامل لا يتم احتسابها كيوم إضافي.
    |
    | extra_day_after_hours:
    | لو الزيادة بعد الأيام الكاملة وصلت لهذا الرقم أو أكثر،
    | يتم احتساب يوم إضافي.
    |
    | مثال:
    | 6 أيام + 1 ساعة = 6 أيام
    | 6 أيام + 2 ساعة = 6 أيام
    | 6 أيام + 6 ساعات = 7 أيام
    |--------------------------------------------------------------------------
    */
    $graceHours = 2;
    $extraDayAfterHours = 6;

    if (!$startAt || !$endAt) {
        return [
            'total_minutes' => 0,
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'billable_days' => 1,
            'grace_hours' => $graceHours,
            'extra_day_after_hours' => $extraDayAfterHours,
            'label' => 'لم يتم تحديد مدة كاملة، تم احتساب يوم واحد افتراضيًا.',
            'billing_label' => 'يوم واحد افتراضيًا',
        ];
    }

    try {
        $start = \Carbon\Carbon::parse($startAt);
        $end = \Carbon\Carbon::parse($endAt);
    } catch (\Throwable $e) {
        return [
            'total_minutes' => 0,
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'billable_days' => 1,
            'grace_hours' => $graceHours,
            'extra_day_after_hours' => $extraDayAfterHours,
            'label' => 'مدة غير صالحة، تم احتساب يوم واحد افتراضيًا.',
            'billing_label' => 'يوم واحد افتراضيًا',
        ];
    }

    if ($end->lessThanOrEqualTo($start)) {
        return [
            'total_minutes' => 0,
            'days' => 0,
            'hours' => 0,
            'minutes' => 0,
            'billable_days' => 1,
            'grace_hours' => $graceHours,
            'extra_day_after_hours' => $extraDayAfterHours,
            'label' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.',
            'billing_label' => 'مدة غير صحيحة',
        ];
    }

    $totalMinutes = $start->diffInMinutes($end);

    $days = intdiv($totalMinutes, 1440);
    $remainingMinutes = $totalMinutes % 1440;

    $hours = intdiv($remainingMinutes, 60);
    $minutes = $remainingMinutes % 60;

    $remainingHoursFloat = $remainingMinutes / 60;

    /*
    |--------------------------------------------------------------------------
    | Billable days policy
    |--------------------------------------------------------------------------
    | 1) لو المدة أقل من يوم، نحسب يوم واحد.
    | 2) لو هناك أيام كاملة وزيادة داخل سماح الخروج، لا نضيف يوم.
    | 3) لو الزيادة وصلت حد احتساب يوم إضافي، نضيف يوم.
    | 4) المنطقة بين السماح وحد اليوم الإضافي نتركها حاليًا بدون يوم إضافي،
    |    ولاحقًا يمكن تحويلها إلى half-day / late checkout fee.
    |--------------------------------------------------------------------------
    */
    $billableDays = max(1, $days);

    $extraDayApplied = false;
    $withinGrace = false;
    $lateCheckoutMiddleZone = false;

    if ($remainingMinutes > 0) {
        if ($remainingHoursFloat <= $graceHours) {
            $withinGrace = true;
        } elseif ($remainingHoursFloat >= $extraDayAfterHours) {
            $billableDays += 1;
            $extraDayApplied = true;
        } else {
            $lateCheckoutMiddleZone = true;
        }
    }

    $parts = [];

    if ($days > 0) {
        $parts[] = $days . ' يوم';
    }

    if ($hours > 0) {
        $parts[] = $hours . ' ساعة';
    }

    if ($minutes > 0) {
        $parts[] = $minutes . ' دقيقة';
    }

    $billingLabel = $billableDays . ' يوم محاسبي';

    if ($withinGrace) {
        $billingLabel .= ' - تم تجاهل الزيادة لأنها داخل سماح الخروج ' . $graceHours . ' ساعة';
    }

    if ($extraDayApplied) {
        $billingLabel .= ' - تم احتساب يوم إضافي بسبب تجاوز ' . $extraDayAfterHours . ' ساعات';
    }

    if ($lateCheckoutMiddleZone) {
        $billingLabel .= ' - الزيادة بعد السماح ولم تصل لحد يوم إضافي';
    }

    return [
        'total_minutes' => $totalMinutes,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes, 

        'billable_days' => $billableDays,

        'grace_hours' => $graceHours,
        'extra_day_after_hours' => $extraDayAfterHours,

        'within_grace' => $withinGrace,
        'extra_day_applied' => $extraDayApplied,
        'late_checkout_middle_zone' => $lateCheckoutMiddleZone,

        'label' => $parts ? implode(' و ', $parts) : 'أقل من دقيقة',
        'billing_label' => $billingLabel,
    ];
}
}