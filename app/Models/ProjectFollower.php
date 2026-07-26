<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A follow request/grant on a project (see the create_project_followers
 * migration). The business decides status + access_level.
 */
class ProjectFollower extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ACCESS_SUMMARY = 'summary';
    public const ACCESS_DETAILED = 'detailed';

    public const ACCESS_LEVELS = [self::ACCESS_SUMMARY, self::ACCESS_DETAILED];

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'access_level',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
