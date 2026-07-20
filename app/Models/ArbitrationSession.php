<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArbitrationSession extends Model
{
    protected $fillable = [
        'dispute_id',
        'arbitrator_id',
        'outcome',
        'fee_type',
        'fee_value',
        'fee_amount',
        'fee_on',
        'fee_terms_set_at',
        'accepted_at',
        'client_percent',
        'business_percent',
        'amount_to_client',
        'amount_to_business',
        'platform_fine_amount',
        'platform_fine_on',
        'platform_fine_reason',
        'compensation_amount',
        'compensation_to',
        'compensation_note',
        'compensation_paid_at',
        'notes',
    ];

    protected $casts = [
        'client_percent' => 'decimal:2',
        'business_percent' => 'decimal:2',
        'amount_to_client' => 'decimal:2',
        'amount_to_business' => 'decimal:2',
        'platform_fine_amount' => 'decimal:2',
        'compensation_amount' => 'decimal:2',
        'compensation_paid_at' => 'datetime',
        'fee_value' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'fee_terms_set_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public const FINE_CONDUCT = 'conduct';
    public const FINE_NON_COMPLIANCE = 'non_compliance';

    /** The only two grounds a fine may rest on. */
    public const FINE_REASONS = [self::FINE_CONDUCT, self::FINE_NON_COMPLIANCE];

    public const FEE_FIXED = 'fixed';
    public const FEE_PERCENT = 'percent';

    /** Accepted but not yet decided. */
    public function isOpen(): bool
    {
        return $this->outcome === null;
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function arbitrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbitrator_id');
    }
}
