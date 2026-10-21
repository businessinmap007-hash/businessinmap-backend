<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business's discount rule (see the create_promotions migration). Priced into the cart by PromotionPricing.
 */
class Promotion extends Model
{
    public const PERCENT = 'percent';
    public const AMOUNT = 'amount';
    public const N_FOR_PRICE_OF = 'n_for_price_of';   // «اشترِ n وادفع ثمن أغلى pay» — also «اشترِ ٢ والثالثة مجانًا» (n=3, pay=2)
    public const SPEND_PERCENT = 'spend_percent';     // «اشترِ بـ min_total فأكثر واحصل على value٪»

    public const KINDS = [self::PERCENT, self::AMOUNT, self::N_FOR_PRICE_OF, self::SPEND_PERCENT];

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_NEW = 'new_customers';

    public const TARGET_ALL = 'all';
    public const TARGET_MENU_SECTION = 'menu_section';
    public const TARGET_MENU_ITEM = 'menu_item';
    public const TARGET_LISTING = 'listing';
    public const TARGET_LISTING_CATEGORY = 'listing_category';

    public const TARGET_TYPES = [self::TARGET_ALL, self::TARGET_MENU_SECTION, self::TARGET_MENU_ITEM, self::TARGET_LISTING, self::TARGET_LISTING_CATEGORY];

    protected $fillable = ['business_id', 'title', 'kind', 'params', 'audience', 'starts_at', 'ends_at', 'max_uses', 'used_count', 'is_active'];

    protected $casts = [
        'params' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    /** Running right now: switched on, inside its dates, and not used up. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gte(now()))
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }

    /** A number out of the rule's parameters. */
    public function param(string $key, float|int $default = 0): float
    {
        return (float) (($this->params ?? [])[$key] ?? $default);
    }
}
