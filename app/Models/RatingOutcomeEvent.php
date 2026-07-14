<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for operation-rating outcomes: one row per
 * operation+party+outcome, so recording the same outcome twice (retries, double
 * webhooks, re-runs) never double-counts the aggregate. Written only through
 * App\Services\Ratings\RatingService.
 */
class RatingOutcomeEvent extends Model
{
    public const OP_BOOKING = 'booking';
    public const OP_ORDER = 'order';

    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_CANCELLED = 'cancelled';
    public const OUTCOME_DISPUTED = 'disputed';

    protected $table = 'rating_outcome_events';

    protected $fillable = [
        'operation_type',
        'operation_id',
        'ratee_user_id',
        'role',
        'outcome',
    ];

    protected $casts = [
        'operation_id' => 'integer',
        'ratee_user_id' => 'integer',
    ];

    public static function outcomes(): array
    {
        return [self::OUTCOME_SUCCESS, self::OUTCOME_CANCELLED, self::OUTCOME_DISPUTED];
    }
}
