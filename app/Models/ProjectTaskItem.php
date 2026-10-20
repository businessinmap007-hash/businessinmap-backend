<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A measurable quantity inside a stage: «الأعمدة — 24 عمود», «الخرسانة — 120 م³», «الطوب — 18000 طوبة». `planned` is the
 * agreed amount (changing it after the plan was agreed is a scope change the customer approves), `done` is what has been
 * executed (the engineer updates it freely). Every change is written to project_item_logs by ProjectItemService.
 */
class ProjectTaskItem extends Model
{
    protected $fillable = ['task_id', 'name', 'unit', 'planned', 'done', 'notes', 'sort_order'];

    protected $casts = ['planned' => 'float', 'done' => 'float', 'sort_order' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    /** 0..1 */
    public function ratio(): float
    {
        return $this->planned > 0 ? min(1.0, max(0.0, $this->done / $this->planned)) : 0.0;
    }
}
