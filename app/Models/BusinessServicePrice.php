<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessServicePrice extends Model
{
    protected $table = 'business_service_prices';

    public const DEFAULT_CURRENCY = 'EGP';
    public const DEFAULT_ITEM_TYPE = 'category';

    /** Table charge modes — how the unit itself is charged. */
    public const CHARGE_STANDARD = 'standard';
    public const CHARGE_FREE = 'free';
    public const CHARGE_RESERVATION_FEE = 'reservation_fee';
    public const CHARGE_MINIMUM = 'minimum_charge';

    public const CHARGE_MODES = [
        self::CHARGE_STANDARD,
        self::CHARGE_FREE,
        self::CHARGE_RESERVATION_FEE,
        self::CHARGE_MINIMUM,
    ];

    protected $fillable = [
        'business_id',
        'child_id',
        'service_id',
        'bookable_item_type',
        'price',
        'charge_mode',
        'charge_amount',
        'currency',
        'is_active',
        'deposit_enabled',
        'deposit_percent',
        'discount_enabled',
        'discount_percent',
    ];

    protected $casts = [
        'business_id'        => 'integer',
        'child_id'           => 'integer',
        'service_id'         => 'integer',
        'bookable_item_type' => 'string',
        'price'              => 'decimal:2',
        'charge_mode'        => 'string',
        'charge_amount'      => 'decimal:2',
        'currency'           => 'string',
        'is_active'          => 'boolean',
        'deposit_enabled'    => 'boolean',
        'deposit_percent'    => 'integer',
        'discount_enabled'   => 'boolean',
        'discount_percent'   => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function categoryChild(): BelongsTo
    {
        return $this->child();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->service();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query, $value = true): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $value);
    }

    public function scopeForBusiness(Builder $query, ?int $businessId): Builder
    {
        if (! $businessId) {
            return $query;
        }

        return $query->where('business_id', (int) $businessId);
    }

    public function scopeForChild(Builder $query, ?int $childId): Builder
    {
        if (! $childId) {
            return $query;
        }

        return $query->where('child_id', (int) $childId);
    }

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('service_id', (int) $serviceId);
    }

    public function scopeForItemType(Builder $query, ?string $itemType): Builder
    {
        $itemType = trim((string) $itemType);

        if ($itemType === '') {
            return $query;
        }

        return $query->where('bookable_item_type', $itemType);
    }

    public function scopeForContext(
        Builder $query,
        ?int $businessId,
        ?int $serviceId,
        ?int $childId = null,
        ?string $itemType = null
    ): Builder {
        return $query
            ->forBusiness($businessId)
            ->forService($serviceId)
            ->forChild($childId)
            ->forItemType($itemType);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Mutators
    |--------------------------------------------------------------------------
    */

    public function setCurrencyAttribute($value): void
    {
        $currency = strtoupper(trim((string) $value));

        $this->attributes['currency'] = $currency !== ''
            ? mb_substr($currency, 0, 3)
            : self::DEFAULT_CURRENCY;
    }

    public function setBookableItemTypeAttribute($value): void
    {
        $type = trim((string) $value);

        $this->attributes['bookable_item_type'] = $type !== ''
            ? $type
            : self::DEFAULT_ITEM_TYPE;
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing Helpers
    |--------------------------------------------------------------------------
    */

    public function baseUnitPrice(): float
    {
        return round((float) ($this->price ?? 0), 2);
    }

    public function chargeMode(): string
    {
        $mode = (string) ($this->charge_mode ?? self::CHARGE_STANDARD);

        return in_array($mode, self::CHARGE_MODES, true) ? $mode : self::CHARGE_STANDARD;
    }

    public function chargeAmount(): float
    {
        return round(max((float) ($this->charge_amount ?? 0), 0), 2);
    }

    /**
     * The unit's own charge before any add-on food/order, given the food total
     * so far (0 for a standalone booking):
     *  - standard        -> the normal price
     *  - free            -> 0 (only food is charged)
     *  - reservation_fee -> the fixed fee
     *  - minimum_charge  -> the greater of the minimum and the food total
     */
    public function resolveBaseCharge(float $foodTotal = 0): float
    {
        $food = round(max($foodTotal, 0), 2);

        return match ($this->chargeMode()) {
            self::CHARGE_FREE => 0.00,
            self::CHARGE_RESERVATION_FEE => $this->chargeAmount(),
            self::CHARGE_MINIMUM => round(max($this->chargeAmount(), $food), 2),
            default => $this->baseUnitPrice(),
        };
    }

    /**
     * The unified invoice total for this offering combined with attached food.
     * Additive for most modes; minimum_charge is max(minimum, food) so it does
     * not double-count. Discount (if enabled) applies to the unit portion only.
     */
    public function unifiedTotal(float $foodTotal = 0, int $quantity = 1): float
    {
        $food = round(max($foodTotal, 0), 2);
        $quantity = max($quantity, 1);

        if ($this->chargeMode() === self::CHARGE_MINIMUM) {
            return round(max($this->chargeAmount(), $food), 2);
        }

        $unit = $this->resolveBaseCharge(0);

        $discountPercent = (bool) ($this->discount_enabled ?? false)
            ? max(0, min((int) ($this->discount_percent ?? 0), 100))
            : 0;

        $unitAfterDiscount = round($unit * $quantity * (1 - $discountPercent / 100), 2);

        return round(max($unitAfterDiscount, 0) + $food, 2);
    }

    public function currencyCode(): string
    {
        $currency = strtoupper(trim((string) $this->currency));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    public function discountPercent(): int
    {
        if (! (bool) $this->discount_enabled) {
            return 0;
        }

        return max(0, min((int) ($this->discount_percent ?? 0), 100));
    }

    public function discountAmount(int $quantity = 1): float
    {
        $quantity = max((int) $quantity, 1);
        $original = round($this->baseUnitPrice() * $quantity, 2);
        $percent = $this->discountPercent();

        if ($original <= 0 || $percent <= 0) {
            return 0.00;
        }

        return round($original * ($percent / 100), 2);
    }

    public function finalPrice(int $quantity = 1): float
    {
        $quantity = max((int) $quantity, 1);
        $original = round($this->baseUnitPrice() * $quantity, 2);

        return max(round($original - $this->discountAmount($quantity), 2), 0);
    }

    public function depositPercent(): int
    {
        if (! (bool) $this->deposit_enabled) {
            return 0;
        }

        return max(0, min((int) ($this->deposit_percent ?? 0), 100));
    }

    public function depositAmount(int $quantity = 1): float
    {
        $percent = $this->depositPercent();

        if ($percent <= 0) {
            return 0.00;
        }

        return round($this->finalPrice($quantity) * ($percent / 100), 2);
    }

    public function priceSnapshot(int $quantity = 1): array
    {
        $quantity = max((int) $quantity, 1);
        $original = round($this->baseUnitPrice() * $quantity, 2);
        $discountAmount = $this->discountAmount($quantity);
        $final = $this->finalPrice($quantity);

        return [
            'business_service_price_id' => (int) $this->id,
            'business_id' => (int) $this->business_id,
            'child_id' => (int) ($this->child_id ?? 0),
            'service_id' => (int) $this->service_id,
            'platform_service_id' => (int) $this->service_id,
            'bookable_item_type' => (string) ($this->bookable_item_type ?: self::DEFAULT_ITEM_TYPE),

            'unit_price' => $this->baseUnitPrice(),
            'quantity' => $quantity,
            'original_price' => $original,

            'discount_enabled' => (bool) $this->discount_enabled,
            'discount_percent' => $this->discountPercent(),
            'discount_amount' => $discountAmount,

            'final_price' => $final,
            'currency' => $this->currencyCode(),

            'deposit_enabled' => (bool) $this->deposit_enabled,
            'deposit_percent' => $this->depositPercent(),
            'deposit_amount' => $this->depositAmount($quantity),

            'is_active' => (bool) $this->is_active,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        $business = $this->business?->name ?: 'Business';
        $child = $this->child?->display_name ?: 'Child';

        $service = $this->service?->name_ar
            ?: $this->service?->name_en
            ?: $this->service?->key
            ?: 'Service';

        $type = $this->bookable_item_type ?: self::DEFAULT_ITEM_TYPE;

        return "{$business} / {$child} / {$service} / {$type}";
    }
}