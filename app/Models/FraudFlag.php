<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A suspected-fraud flag raised by the rating-graph scan. Advisory only — an
 * admin acts on it (fine/ban) or dismisses it. See FraudDetectionService.
 */
class FraudFlag extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'user_id', 'score', 'total_operations', 'disputed_ratio', 'cancelled_ratio',
        'reasons', 'status', 'flagged_at', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'score' => 'decimal:4',
        'total_operations' => 'integer',
        'disputed_ratio' => 'decimal:4',
        'cancelled_ratio' => 'decimal:4',
        'reasons' => 'array',
        'flagged_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
