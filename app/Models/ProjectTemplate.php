<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A ready stage plan for a trade («تشطيب شقة», «صيانة سيارة»…). A template with no trade rows is general. Platform data,
 * seeded — see ProjectTemplatesSeeder; a business copies it into its own project and edits the copy.
 */
class ProjectTemplate extends Model
{
    protected $fillable = ['code', 'title_ar', 'title_en', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectTemplateStage::class, 'template_id')->orderBy('sort_order')->orderBy('id');
    }

    public function title(): string
    {
        return app()->getLocale() === 'en' ? (string) $this->title_en : (string) $this->title_ar;
    }

    /** @return list<int> the trades this template is offered to; empty = general */
    public function childIds(): array
    {
        return DB::table('project_template_children')->where('template_id', $this->id)->pluck('child_id')->map(fn ($i) => (int) $i)->all();
    }
}
