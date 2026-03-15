<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookableItemBlockedSlot extends Model
{
    protected $table = 'bookable_item_blocked_slots';

    protected $fillable = [
        'bookable_item_id',
        'business_id',
        'platform_service_id',
        'block_type',
        'starts_at',
        'ends_at',
        'reason',
        'notes',
        'source_type',
        'source_id',
        'created_by',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public const TYPE_MANUAL = 'manual';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_HOLIDAY = 'holiday';
    public const TYPE_BOOKING_HOLD = 'booking_hold';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_ADMIN = 'admin';

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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBookableItem(Builder $query, int $bookableItemId): Builder
    {
        return $query->where('bookable_item_id', $bookableItemId);
    }

    public function scopeOverlapping(Builder $query, $startsAt, $endsAt): Builder
    {
        return $query->where(function (Builder $q) use ($startsAt, $endsAt) {
            $q->where('starts_at', '<', $endsAt)
              ->where('ends_at', '>', $startsAt);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('starts_at')->orderBy('id');
    }
}
