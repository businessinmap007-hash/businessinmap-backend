<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A project the business shows as a post, with its customer's recorded consent (see the create_project_showcases migration). */
class ProjectShowcase extends Model
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const WITHDRAWN = 'withdrawn';

    /** The key a post carries in `subject_type` so the feed knows to draw a verified-results card. */
    public const POST_SUBJECT = 'project_showcase';

    protected $fillable = [
        'project_id', 'business_id', 'post_id', 'public_title', 'note', 'show_photos', 'status',
        'requested_at', 'decided_at', 'decision_note', 'decided_by',
    ];

    protected $casts = [
        'show_photos' => 'boolean',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isLive(): bool
    {
        return $this->status === self::APPROVED;
    }
}
