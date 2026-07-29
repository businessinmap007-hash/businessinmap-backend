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
    // The ruling outcomes: a dispute was decided AGAINST this party (fault) or
    // IN THEIR FAVOUR (vindicated). Deliberately NOT in outcomes() — they overlay
    // an operation already counted as `disputed` and are written only by
    // RatingService::recordDisputeRuling, which must not bump total_operations
    // the way recordOutcome() does for the three above. Mutually exclusive per
    // party per operation (a party is never both at fault and vindicated on one).
    public const OUTCOME_FAULT = 'fault';
    public const OUTCOME_VINDICATED = 'vindicated';

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
