<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookableItemPriceRule extends Model
{
    protected $table = 'bookable_item_price_rules';

    protected $fillable = [
        'bookable_item_id',
        'business_id',
        'platform_service_id',
        'rule_type',
        'start_date',
        'end_date',
        'weekday',
        'price_type',
        'price_value',
        'currency',
        'min_quantity',
        'max_quantity',
        'priority',
        'is_active',
        'title',
        'notes',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'weekday' => 'integer',
        'price_value' => 'decimal:2',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public const RULE_DEFAULT = 'default';
    public const RULE_WEEKDAY = 'weekday';
    public const RULE_DATE_RANGE = 'date_range';
    public const RULE_SEASON = 'season';
    public const RULE_SPECIAL_DAY = 'special_day';

    public const PRICE_FIXED = 'fixed';
    public const PRICE_DELTA = 'delta';
    public const PRICE_PERCENT = 'percent';

    public function bookableItem(): BelongsTo
    {
        return $this->belongsTo(BookableItem::class, 'bookable_item_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function platformService(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBookableItem(Builder $query, int $bookableItemId): Builder
    {
        return $query->where('bookable_item_id', $bookableItemId);
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        $date = is_string($date) ? $date : optional($date)->format('Y-m-d');

        return $query->where(function (Builder $q) use ($date) {
            $q->where(function (Builder $w) use ($date) {
                $w->whereNotNull('start_date')
                  ->whereNotNull('end_date')
                  ->whereDate('start_date', '<=', $date)
                  ->whereDate('end_date', '>=', $date);
            })->orWhere(function (Builder $w) {
                $w->whereNull('start_date')->whereNull('end_date');
            });
        });
    }

    public function scopeForWeekday(Builder $query, ?int $weekday): Builder
    {
        if ($weekday === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($weekday) {
            $q->whereNull('weekday')
              ->orWhere('weekday', $weekday);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderByDesc('id');
    }
}
