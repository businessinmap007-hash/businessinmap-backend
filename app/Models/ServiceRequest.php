<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's need, posted for the businesses of a trade to answer (see the migration). `kind` says what it is and
 * therefore what its `params` hold and who may answer; the kinds live in {@see \App\Services\Requests\RequestKinds}.
 */
class ServiceRequest extends Model
{
    protected $table = 'service_requests';

    public const KIND_RIDE = 'ride';
    public const KIND_CAR_WANTED = 'car_wanted';

    public const STATUS_OPEN = 'open';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'kind', 'requester_id', 'category_child_id', 'governorate_id', 'city_id', 'title', 'details', 'params',
        'starts_at', 'budget', 'suggested_price', 'currency', 'status', 'accepted_offer_id',
        'operation_type', 'operation_id', 'expires_at',
    ];

    protected $casts = [
        'params' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'budget' => 'decimal:2',
        'suggested_price' => 'decimal:2',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ServiceRequestOffer::class, 'service_request_id');
    }

    /** Still taking offers: open, and not past its time. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** `open` that ran out of time reads as `expired` — the row is never rewritten for it. */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_OPEN && $this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        return (string) $this->status;
    }
}
