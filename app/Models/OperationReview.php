<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A subjective star review (1–5) one party gives the other for a real, completed
 * operation. Written only through App\Services\Ratings\RatingService, which
 * enforces the "must have actually dealt" rule and keeps the aggregate in step.
 */
class OperationReview extends Model
{
    protected $table = 'operation_reviews';

    protected $fillable = [
        'operation_type',
        'operation_id',
        'rater_id',
        'ratee_id',
        'ratee_role',
        'stars',
        'comment',
    ];

    protected $casts = [
        'operation_id' => 'integer',
        'rater_id' => 'integer',
        'ratee_id' => 'integer',
        'stars' => 'integer',
    ];

    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee()
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }
}
