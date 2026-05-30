<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\CategoryChildServiceFee;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceExecutionEngine
{
    public const EXECUTION_FEE_CODE = WalletFeeService::DEFAULT_FEE_CODE;

    public function __construct(
        protected WalletFeeService $walletFeeService,
        protected BookingDepositService $bookingDepositService,
        protected BookablePricingService $bookablePricingService,
        protected BookableAvailabilityService $bookableAvailabilityService,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare / Preview
    |--------------------------------------------------------------------------
    */

    public function prepare(
        int $businessId,
        int $serviceId,
        ?int $bookableId = null,
        int $quantity = 1,
        mixed $pricingDate = null
    ): array {
        $quantity = max($quantity, 1);

        [$business, $categoryId, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        $service = PlatformService::query()
            ->where('id', $serviceId)
            ->where('is_active', 1)
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير موجودة أو غير مفعلة.',
            ]);
        }

        $businessPrice = $this->resolveBusinessServicePrice($businessId, $serviceId, $childId);

        if (! $businessPrice) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة لهذا البزنس داخل هذا القسم الفرعي.',
            ]);
        }

        $bookable = null;

        if ($bookableId) {
            $bookable = $this->resolveBookableItem($businessId, $serviceId, $bookableId);
        }

        $priceBreakdown = $this->resolvePriceBreakdown(
            service: $service,
            businessPrice: $businessPrice,
            bookable: $bookable,
            quantity: $quantity,
            pricingDate: $pricingDate
        );

        $depositPolicy = $this->resolveDepositPolicy(
            service: $service,
            businessPrice: $businessPrice,
            price: (float) $priceBreakdown['final_price'],
            bookable: $bookable
        );

        $feeSnapshot = $this->resolveExecutionFeeSnapshot(
            businessId: $businessId,
            categoryId: $categoryId,
            serviceId: $serviceId,
            childId: $childId
        );

        return [
            'business' => $business,
            'service' => $service,
            'business_price' => $businessPrice,
            'bookable' => $bookable,

            'business_id' => $businessId,
            'business_category_id' => $categoryId,
            'business_child_id' => $childId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'platform_service_id' => $serviceId,

            'price' => (float) $priceBreakdown['final_price'],
            'price_breakdown' => $priceBreakdown,
            'deposit_policy' => $depositPolicy,

            'service_fee_row' => $feeSnapshot['row'] ?? null,
            'service_fee_rows' => [
                'business' => $feeSnapshot['business'] ?? null,
                'client' => $feeSnapshot['client'] ?? null,
            ],
            'fee_snapshot' => $feeSnapshot,
        ];
    }

    public function preview(
        int $businessId,
        int $serviceId,
        ?int $bookableId = null,
        int $quantity = 1,
        mixed $startsAt = null,
        mixed $endsAt = null
    ): array {
        $calc = $this->prepare($businessId, $serviceId, $bookableId, $quantity);

        $availability = null;

        if ($calc['bookable'] && $startsAt && $endsAt) {
            $availability = $this->bookableAvailabilityService->check(
                $calc['bookable'],
                $startsAt,
                $endsAt
            );
        }

        return array_merge($calc, [
            'availability' => $availability,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Booking Meta
    |--------------------------------------------------------------------------
    */

    public function buildBookingMeta(
        array $existingMeta,
        array $calc,
        ?BookableItem $bookable = null
    ): array {
        $meta = $existingMeta;

        $service = $calc['service'];
        $business = $calc['business'];
        $businessPrice = $calc['business_price'];
        $priceBreakdown = $calc['price_breakdown'];
        $depositPolicy = $calc['deposit_policy'];
        $feeSnapshot = $calc['fee_snapshot'];

        $meta['platform_service'] = [
            'id' => (int) $service->id,
            'key' => (string) $service->key,
            'name_ar' => (string) ($service->name_ar ?? ''),
            'name_en' => (string) ($service->name_en ?? ''),
        ];

        $meta['business_context'] = [
            'business_id' => (int) $business->id,
            'category_id' => (int) ($business->category_id ?? 0),
            'category_child_id' => (int) ($business->category_child_id ?? 0),
        ];

        $meta['pricing'] = [
            'original_price' => (float) $priceBreakdown['original_price'],
            'discount_enabled' => (bool) $priceBreakdown['discount_enabled'],
            'discount_percent' => (int) $priceBreakdown['discount_percent'],
            'discount_amount' => (float) $priceBreakdown['discount_amount'],
            'unit_price' => (float) $priceBreakdown['unit_price'],
            'quantity' => (int) $priceBreakdown['quantity'],
            'final_price' => (float) $priceBreakdown['final_price'],
            'price' => (float) $priceBreakdown['final_price'],
            'currency' => (string) ($priceBreakdown['currency'] ?? 'EGP'),
            'source' => (string) $priceBreakdown['source'],
            'business_service_price_id' => (int) $businessPrice->id,
            'business_service_price_child_id' => (int) ($businessPrice->child_id ?? 0),
            'pricing_source' => (string) ($priceBreakdown['pricing_source'] ?? $priceBreakdown['source'] ?? ''),
            'bookable_rule' => $priceBreakdown['bookable_rule'] ?? null,
            'bookable_rules' => $priceBreakdown['bookable_rules'] ?? [],
            'bookable_breakdown' => $priceBreakdown['bookable_breakdown'] ?? [],
        ];

        $meta['service_fees_snapshot'] = $feeSnapshot;
        $meta['deposit_policy'] = $depositPolicy;

        if ($bookable) {
            $meta['bookable_item'] = [
                'id' => (int) $bookable->id,
                'title' => (string) $bookable->title,
                'code' => (string) ($bookable->code ?? ''),
                'item_type' => (string) ($bookable->item_type ?? ''),
                'price' => (float) ($bookable->price ?? 0),
                'deposit_enabled' => (bool) ($bookable->deposit_enabled ?? false),
                'deposit_percent' => (int) ($bookable->deposit_percent ?? 0),
            ];
        } else {
            unset($meta['bookable_item']);
        }

        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];

        $meta['_execution_fee']['code'] = self::EXECUTION_FEE_CODE;
        $meta['_execution_fee']['category_id'] = (int) ($calc['business_category_id'] ?? 0);
        $meta['_execution_fee']['child_id'] = (int) ($calc['business_child_id'] ?? 0);
        $meta['_execution_fee']['service_id'] = (int) ($calc['service_id'] ?? 0);
        $meta['_execution_fee']['platform_service_id'] = (int) ($calc['platform_service_id'] ?? $calc['service_id'] ?? 0);

        $meta['_execution_fee']['fee_row_id'] = $feeSnapshot['fee_row_id'] ?? null;
        $meta['_execution_fee']['category_child_service_fee_id'] = $feeSnapshot['category_child_service_fee_id'] ?? null;

        $meta['_execution_fee']['snapshot'] = [
            'business' => $feeSnapshot['business'] ?? null,
            'client' => $feeSnapshot['client'] ?? null,
        ];

        $meta['_execution_fee']['client_amount'] = (float) ($meta['_execution_fee']['client_amount'] ?? 0);
        $meta['_execution_fee']['business_amount'] = (float) ($meta['_execution_fee']['business_amount'] ?? 0);
        $meta['_execution_fee']['charged_at'] = $meta['_execution_fee']['charged_at'] ?? null;
        $meta['_execution_fee']['transactions'] = $meta['_execution_fee']['transactions'] ?? [];

        return $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Move To In Progress
    |--------------------------------------------------------------------------
    */

    public function moveBookingToInProgress(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->refresh();

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

            $this->chargeExecutionFeeOnce($booking);

            $booking->status = Booking::STATUS_IN_PROGRESS;
            $booking->save();
        });
    }

    public function chargeExecutionFeeOnce(Booking $booking): void
    {
        $booking->refresh();

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $meta['_execution_fee'] = $meta['_execution_fee'] ?? [];

        if (! empty($meta['_execution_fee']['charged_at'])) {
            return;
        }

        $transactions = $this->walletFeeService->applyBookingFees(
            $booking,
            self::EXECUTION_FEE_CODE
        );

        $clientAmount = 0.0;
        $businessAmount = 0.0;
        $txMap = [];

        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $payer = (string) data_get($tx->meta, 'payer', '');

            if ($payer === CategoryChildServiceFee::PAYER_CLIENT) {
                $clientAmount += $amount;
            }

            if ($payer === CategoryChildServiceFee::PAYER_BUSINESS) {
                $businessAmount += $amount;
            }

            $txMap[] = [
                'id' => (int) $tx->id,
                'user_id' => (int) $tx->user_id,
                'payer' => $payer,
                'amount' => $amount,
                'type' => (string) $tx->type,
                'direction' => (string) $tx->direction,
                'status' => (string) $tx->status,
                'category_child_service_fee_id' => data_get($tx->meta, 'category_child_service_fee_id'),
                'service_fee_id' => data_get($tx->meta, 'service_fee_id'),
                'fee_row_id' => data_get($tx->meta, 'fee_row_id'),
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

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    public function resolvePriceBreakdown(
        PlatformService $service,
        BusinessServicePrice $businessPrice,
        ?BookableItem $bookable = null,
        int $quantity = 1,
        mixed $pricingDate = null
    ): array {
        $quantity = max($quantity, 1);

        if ($bookable) {
            $unitPrice = round((float) ($bookable->price ?? 0), 2);
            $originalPrice = round($unitPrice * $quantity, 2);

            return [
                'source' => 'bookable_item',
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'original_price' => $originalPrice,
                'discount_enabled' => false,
                'discount_percent' => 0,
                'discount_amount' => 0.00,
                'final_price' => $originalPrice,
                'currency' => (string) ($businessPrice->currency ?: 'EGP'),
            ];
        }

        $unitPrice = round((float) ($businessPrice->price ?? 0), 2);

        if ($unitPrice <= 0 && isset($businessPrice->base_price)) {
            $unitPrice = round((float) ($businessPrice->base_price ?? 0), 2);
        }

        $originalPrice = round($unitPrice * $quantity, 2);

        $discountEnabled = (bool) ($businessPrice->discount_enabled ?? false);
        $discountPercent = $discountEnabled ? (int) ($businessPrice->discount_percent ?? 0) : 0;
        $discountPercent = max(0, min($discountPercent, 100));

        $discountAmount = $discountEnabled
            ? round($originalPrice * ($discountPercent / 100), 2)
            : 0.00;

        $finalPrice = max(round($originalPrice - $discountAmount, 2), 0);

        return [
            'source' => 'business_service_price',
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'original_price' => $originalPrice,
            'discount_enabled' => $discountEnabled,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'currency' => (string) ($businessPrice->currency ?: 'EGP'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    */

    public function resolveDepositPolicy(
        PlatformService $service,
        BusinessServicePrice $businessPrice,
        float $price,
        ?BookableItem $bookable = null
    ): array {
        $serviceSupportsDeposit = (bool) ($service->supports_deposit ?? false);
        $serviceMaxPercent = (int) ($service->max_deposit_percent ?? 0);

        $businessDepositEnabled = (bool) ($businessPrice->deposit_enabled ?? false);
        $businessDepositPercent = (int) ($businessPrice->deposit_percent ?? 0);

        $effectiveDepositEnabled = $businessDepositEnabled;
        $effectiveDepositPercent = $businessDepositPercent;
        $source = 'business_service_price';

        if ($bookable && (bool) ($bookable->deposit_enabled ?? false)) {
            $effectiveDepositEnabled = true;
            $effectiveDepositPercent = (int) ($bookable->deposit_percent ?? 0);
            $source = 'bookable_item';
        }

        if ($serviceMaxPercent > 0 && $effectiveDepositPercent > $serviceMaxPercent) {
            $effectiveDepositPercent = $serviceMaxPercent;
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
            'bookable_deposit_enabled' => $bookable ? (bool) ($bookable->deposit_enabled ?? false) : false,
            'bookable_deposit_percent' => $bookable ? (int) ($bookable->deposit_percent ?? 0) : 0,
            'required' => $required,
            'amount' => $amount,
            'hold' => $amount,
            'configured_percent' => $effectiveDepositPercent,
            'source' => $source,
        ];
    }

    public function depositPolicy(Booking $booking): array
    {
        $booking->loadMissing([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
            'business:id,name,type,category_id,category_child_id',
            'bookable',
        ]);

        $childId = (int) ($booking->business?->category_child_id ?? 0);

        $businessPrice = $this->resolveBusinessServicePrice(
            businessId: (int) $booking->business_id,
            serviceId: (int) $booking->service_id,
            childId: $childId
        );

        if (! $booking->service || ! $businessPrice) {
            return [
                'required' => false,
                'amount' => 0.00,
                'hold' => 0.00,
                'configured_percent' => 0,
                'source' => 'none',
            ];
        }

        $bookable = $booking->bookable instanceof BookableItem
            ? $booking->bookable
            : null;

        $price = (float) ($booking->price ?? 0);

        if ($price <= 0) {
            $price = (float) ($booking->total_price ?? 0);
        }

        return $this->resolveDepositPolicy(
            service: $booking->service,
            businessPrice: $businessPrice,
            price: $price,
            bookable: $bookable
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Business / Bookable Resolution
    |--------------------------------------------------------------------------
    */

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

    protected function resolveBookableItem(int $businessId, int $serviceId, int $bookableId): ?BookableItem
    {
        $bookable = BookableItem::query()
            ->where('id', $bookableId)
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->first();

        if (! $bookable) {
            throw ValidationException::withMessages([
                'bookable_id' => 'العنصر القابل للحجز غير موجود أو غير تابع لهذا البزنس/الخدمة.',
            ]);
        }

        return $bookable;
    }

    /*
    |--------------------------------------------------------------------------
    | Service Fees
    |--------------------------------------------------------------------------
    */

    protected function resolveExecutionFeeSnapshot(
        int $businessId,
        int $categoryId,
        int $serviceId,
        int $childId = 0
    ): array {
        $row = $this->resolveChildServiceFeeRow($categoryId, $childId, $serviceId);

        return [
            'row' => $row,
            'business_id' => $businessId,
            'category_id' => $categoryId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'platform_service_id' => $serviceId,

            'fee_code' => self::EXECUTION_FEE_CODE,
            'fee_row_id' => $row ? (int) $row->id : null,
            'category_child_service_fee_id' => $row ? (int) $row->id : null,

            'business' => $row ? $row->toFeeSnapshot(CategoryChildServiceFee::PAYER_BUSINESS) : null,
            'client' => $row ? $row->toFeeSnapshot(CategoryChildServiceFee::PAYER_CLIENT) : null,
        ];
    }

    protected function resolveChildServiceFeeRow(int $categoryId, int $childId, int $serviceId): ?CategoryChildServiceFee
    {
        if ($categoryId > 0 && $childId > 0 && $serviceId > 0) {
            return CategoryChildServiceFee::activeForRootChild($categoryId, $childId, $serviceId);
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy-safe fallback
        |--------------------------------------------------------------------------
        | لا نستخدم activeForPair مباشرة إلا إذا كان هناك صف واحد فقط لهذا
        | child/service، حتى لا نقرأ رسوم root آخر بالخطأ.
        |--------------------------------------------------------------------------
        */
        if ($childId > 0 && $serviceId > 0) {
            $rows = CategoryChildServiceFee::query()
                ->active(1)
                ->forPair($childId, $serviceId)
                ->ordered()
                ->limit(2)
                ->get();

            if ($rows->count() === 1) {
                return $rows->first();
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Confirmations
    |--------------------------------------------------------------------------
    */

    protected function latestDeposit(Booking $booking): ?Deposit
    {
        return $this->bookingDepositService->latestDeposit($booking);
    }

    protected function resolveConfirmState(Booking $booking, ?Deposit $deposit): array
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $confirm = is_array($meta['_start_confirm'] ?? null) ? $meta['_start_confirm'] : [];

        $metaClientConfirmed = ! empty($confirm['client']);
        $metaBusinessConfirmed = ! empty($confirm['business']);

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
}