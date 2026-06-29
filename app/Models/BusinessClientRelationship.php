<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessClientRelationship extends Model
{
    protected $table = 'business_client_relationships';

    protected $fillable = [
        'business_id',
        'client_id',
        'total_operations',
        'completed_operations',
        'cancelled_operations',
        'rejected_operations',
        'disputed_operations',
        'client_no_show_count',
        'business_cancelled_count',
        'total_value',
        'completed_value',
        'avg_client_rating_for_business',
        'avg_business_rating_for_client',
        'client_trust_score_for_business',
        'business_trust_score_for_client',
        'last_operation_at',
        'last_completed_at',
        'last_problem_at',
        'is_trusted_client',
        'trusted_type',
        'trusted_at',
        'trusted_by',
        'trust_notes',
        'meta',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'client_id' => 'integer',
        'total_operations' => 'integer',
        'completed_operations' => 'integer',
        'cancelled_operations' => 'integer',
        'rejected_operations' => 'integer',
        'disputed_operations' => 'integer',
        'client_no_show_count' => 'integer',
        'business_cancelled_count' => 'integer',
        'total_value' => 'decimal:2',
        'completed_value' => 'decimal:2',
        'avg_client_rating_for_business' => 'decimal:2',
        'avg_business_rating_for_client' => 'decimal:2',
        'client_trust_score_for_business' => 'decimal:2',
        'business_trust_score_for_client' => 'decimal:2',
        'last_operation_at' => 'datetime',
        'last_completed_at' => 'datetime',
        'last_problem_at' => 'datetime',
        'is_trusted_client' => 'boolean',
        'trusted_at' => 'datetime',
        'trusted_by' => 'integer',
        'meta' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function trustedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trusted_by');
    }

    public function successRate(): float
    {
        $total = max((int) $this->total_operations, 0);

        if ($total <= 0) {
            return 0.0;
        }

        return round(((int) $this->completed_operations / $total) * 100, 2);
    }

    public function problemRate(): float
    {
        $total = max((int) $this->total_operations, 0);

        if ($total <= 0) {
            return 0.0;
        }

        $problems = (int) $this->cancelled_operations
            + (int) $this->rejected_operations
            + (int) $this->disputed_operations;

        return round(($problems / $total) * 100, 2);
    }
}
