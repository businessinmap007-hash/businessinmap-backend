<?php

namespace App\Models;

use App\Services\Payments\GatewayChargeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer→merchant payment intent. Created `pending`, settled `paid` by the
 * gateway callback. Unlike a wallet top-up it credits NO platform wallet — the
 * money settles into the merchant's own gateway account (when routed_to =
 * merchant) or the platform account for later manual payout. See
 * [[fawry-submerchant-routing]].
 */
class MerchantPayment extends Model implements GatewayChargeable
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const ROUTED_MERCHANT = 'merchant';
    public const ROUTED_PLATFORM = 'platform';

    protected $fillable = [
        'customer_id',
        'business_id',
        'gateway',
        'routed_to',
        'merchant_ref',
        'gateway_ref',
        'method',
        'amount',
        'currency',
        'status',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'business_id' => 'integer',
        'amount' => 'decimal:2',
        'meta' => 'array',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // ── GatewayChargeable ──
    public function chargeRef(): string
    {
        return (string) $this->merchant_ref;
    }

    public function chargeAmount(): float
    {
        return (float) $this->amount;
    }

    public function chargeCustomerRef(): string
    {
        return (string) $this->customer_id;
    }

    public function chargeItemId(): string
    {
        return 'MP-' . $this->id;
    }

    public function chargeDescription(): string
    {
        return 'BIM merchant payment';
    }
}
