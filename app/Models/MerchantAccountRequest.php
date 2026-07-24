<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's application for a Fawry merchant sub-account. Reviewed by an admin,
 * who provisions the merchant_payment_accounts row on approval. See
 * [[fawry-submerchant-routing]].
 */
class MerchantAccountRequest extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_id',
        'status',
        'note',
        'decision_note',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
