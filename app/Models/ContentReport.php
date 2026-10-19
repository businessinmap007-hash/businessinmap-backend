<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's report of one post or listing — see the create_content_reports migration. */
class ContentReport extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACTIONED = 'actioned';

    protected $fillable = ['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details', 'status', 'handled_by', 'handled_at', 'action'];

    protected $casts = ['handled_at' => 'datetime'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
