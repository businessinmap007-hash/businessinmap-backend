<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user, per-role aggregate of operation outcomes. Rates are derived, not
 * stored, so they can never drift from the counts. Written only through
 * App\Services\Ratings\RatingService.
 */
class UserOperationRating extends Model
{
    public const ROLE_CLIENT = 'client';
    public const ROLE_BUSINESS = 'business';

    protected $table = 'user_operation_ratings';

    protected $fillable = [
        'user_id',
        'role',
        'total_operations',
        'success_count',
        'cancelled_count',
        'disputed_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'total_operations' => 'integer',
        'success_count' => 'integer',
        'cancelled_count' => 'integer',
        'disputed_count' => 'integer',
    ];

    public static function roles(): array
    {
        return [self::ROLE_CLIENT, self::ROLE_BUSINESS];
    }

    public function successRate(): float
    {
        return $this->rate($this->success_count);
    }

    public function cancelRate(): float
    {
        return $this->rate($this->cancelled_count);
    }

    public function disputeRate(): float
    {
        return $this->rate($this->disputed_count);
    }

    private function rate(int $count): float
    {
        $total = (int) $this->total_operations;

        return $total > 0 ? round($count / $total * 100, 1) : 0.0;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
