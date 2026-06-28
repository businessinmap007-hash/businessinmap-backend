<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BusinessPartnership extends Model
{
    public const TYPE_HOTEL_ALLOTMENT = 'hotel_allotment';
    public const TYPE_RESELLER = 'reseller';
    public const TYPE_SUPPLIER = 'supplier';
    public const TYPE_AGENCY = 'agency';
    public const TYPE_CORPORATE = 'corporate';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'business_partnerships';

    protected $fillable = [
        'owner_business_id',
        'partner_business_id',
        'relationship_type',
        'status',
        'starts_at',
        'ends_at',
        'approval_required',
        'created_by',
        'approved_by',
        'approved_at',
        'terms',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approved_at' => 'datetime',
        'approval_required' => 'boolean',
        'terms' => 'array',
        'meta' => 'array',
    ];

    public static function relationshipTypes(): array
    {
        return [
            self::TYPE_HOTEL_ALLOTMENT => 'Hotel Allotment',
            self::TYPE_RESELLER => 'Reseller',
            self::TYPE_SUPPLIER => 'Supplier',
            self::TYPE_AGENCY => 'Agency',
            self::TYPE_CORPORATE => 'Corporate',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Expired',
        ];
    }

    public function ownerBusiness()
    {
        return $this->belongsTo(User::class, 'owner_business_id');
    }

    public function partnerBusiness()
    {
        return $this->belongsTo(User::class, 'partner_business_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocations()
    {
        return $this->hasMany(BookableAllocation::class, 'partnership_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForOwner(Builder $query, int $ownerBusinessId): Builder
    {
        return $query->where('owner_business_id', $ownerBusinessId);
    }

    public function scopeForPartner(Builder $query, int $partnerBusinessId): Builder
    {
        return $query->where('partner_business_id', $partnerBusinessId);
    }

    public function isActive(): bool
    {
        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function displayName(): string
    {
        return trim(($this->ownerBusiness?->name ?: 'Owner') . ' ↔ ' . ($this->partnerBusiness?->name ?: 'Partner'));
    }
}
