<?php

namespace App\Models;

use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, fixed-composition combo — "وجبة العيلة" — priced either as a flat
 * amount for the whole bundle, or as the live sum of its components' own
 * current prices minus a discount. Cart-facing as its own offering kind
 * ({@see \App\Services\CustomerCartService}), not a MenuItem: it carries no
 * section/sale-unit/stock concerns of its own, only a name and a component
 * list.
 */
class MenuBundle extends Model
{
    use HasLocalizedFields;

    protected $table = 'menu_bundles';

    public const PRICING_FIXED = 'fixed';

    public const PRICING_DISCOUNT_PERCENT = 'discount_percent';

    public const PRICING_DISCOUNT_FIXED = 'discount_fixed';

    public const PRICING_MODES = [
        self::PRICING_FIXED,
        self::PRICING_DISCOUNT_PERCENT,
        self::PRICING_DISCOUNT_FIXED,
    ];

    protected $fillable = [
        'business_id',
        'name_ar',
        'name_en',
        'pricing_mode',
        'fixed_price',
        'discount_value',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'fixed_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuBundleItem::class, 'menu_bundle_id')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Sum of each component's own current price × its qty in the bundle. */
    public function componentsSubtotal(): float
    {
        return round(
            $this->items->sum(fn (MenuBundleItem $i) => (float) ($i->menuItem->base_price ?? 0) * $i->qty),
            2
        );
    }

    /** What the bundle actually costs right now, per its pricing_mode. */
    public function price(): float
    {
        return match ($this->pricing_mode) {
            self::PRICING_DISCOUNT_PERCENT => max(0, round(
                $this->componentsSubtotal() * (1 - (float) $this->discount_value / 100),
                2
            )),
            self::PRICING_DISCOUNT_FIXED => max(0, round(
                $this->componentsSubtotal() - (float) $this->discount_value,
                2
            )),
            default => round((float) $this->fixed_price, 2),
        };
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Bundle #' . $this->id)));
    }

    /**
     * A bundle already has its own real, merchant-given name — no line/
     * modifier vocabulary to fold in the way {@see \App\Models\Concerns\HasOfferingOptions}
     * does for a MenuItem. Exists only so `OrderItem::booted()`'s generic
     * `method_exists($offering, 'offeringLabel')` snapshot hook works for
     * this offering kind too.
     */
    public function offeringLabel(?string $fallback = null): string
    {
        return $fallback ?? $this->display_name;
    }
}
