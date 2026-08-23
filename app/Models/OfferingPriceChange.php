<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One price move on one priced row.
 *
 * Written by {@see \App\Models\Concerns\RecordsPriceHistory} and read by
 * {@see \App\Services\Offers\OfferEligibility}. Nothing else writes it and
 * nothing edits it — a history somebody can revise is not a history.
 */
class OfferingPriceChange extends Model
{
    protected $table = 'offering_price_changes';

    public $timestamps = false;

    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'business_id',
        'old_price',
        'new_price',
        'currency',
        'is_increase',
        'source',
        'changed_at',
    ];

    protected $casts = [
        'priceable_id' => 'integer',
        'business_id' => 'integer',
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'is_increase' => 'boolean',
        'changed_at' => 'datetime',
    ];

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Only the moves that went UP. A cut is never what an offer hides. */
    public function scopeIncreases(Builder $query): Builder
    {
        return $query->where('is_increase', true);
    }

    public function scopeFor(Builder $query, Model $priceable): Builder
    {
        return $query
            ->where('priceable_type', $priceable->getMorphClass())
            ->where('priceable_id', $priceable->getKey());
    }
}
