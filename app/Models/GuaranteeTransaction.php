<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuaranteeTransaction extends Model
{
    protected $table = 'guarantee_transactions';

    protected $fillable = [
        'user_id',
        'user_guarantee_id',
        'type',
        'amount',
        'coverage_amount',
        'balance_before',
        'balance_after',
        'locked_before',
        'locked_after',
        'reference_type',
        'reference_id',
        'reason',
        'idempotency_key',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'user_guarantee_id' => 'integer',
        'amount' => 'decimal:2',
        'coverage_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'locked_before' => 'decimal:2',
        'locked_after' => 'decimal:2',
        'reference_id' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guarantee(): BelongsTo
    {
        return $this->belongsTo(UserGuarantee::class, 'user_guarantee_id');
    }
}