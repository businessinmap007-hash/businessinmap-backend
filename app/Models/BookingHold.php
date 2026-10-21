<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A room held for someone who is filling in a booking. See the create_booking_holds migration and {@see \App\Services\BookingHolds}. */
class BookingHold extends Model
{
    protected $fillable = ['bookable_item_id', 'user_id', 'seller_business_id', 'starts_at', 'ends_at', 'quantity', 'expires_at'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'expires_at' => 'datetime'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(BookableItem::class, 'bookable_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_business_id');
    }

    /** Holds still running. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
