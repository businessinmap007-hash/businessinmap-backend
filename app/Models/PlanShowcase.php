<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A training / nutrition plan shown as a post, with its client's recorded consent (see the plan_showcases migration). */
class PlanShowcase extends Model
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const WITHDRAWN = 'withdrawn';

    public const POST_SUBJECT = 'plan_showcase';

    protected $fillable = [
        'training_plan_id', 'trainer_id', 'post_id', 'public_title', 'note', 'status',
        'requested_at', 'decided_at', 'decision_note', 'decided_by',
    ];

    protected $casts = ['requested_at' => 'datetime', 'decided_at' => 'datetime'];

    public function isLive(): bool
    {
        return $this->status === self::APPROVED;
    }
}
