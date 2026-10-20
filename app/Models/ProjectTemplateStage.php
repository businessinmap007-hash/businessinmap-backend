<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One stage of a {@see ProjectTemplate}. */
class ProjectTemplateStage extends Model
{
    protected $fillable = ['template_id', 'sort_order', 'title_ar', 'title_en', 'duration_days', 'requires_photo', 'requires_approval'];

    protected $casts = ['requires_photo' => 'boolean', 'requires_approval' => 'boolean', 'duration_days' => 'integer'];

    public function title(): string
    {
        return app()->getLocale() === 'en' ? (string) $this->title_en : (string) $this->title_ar;
    }
}
