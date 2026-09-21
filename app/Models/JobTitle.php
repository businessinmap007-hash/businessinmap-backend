<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobTitle extends Model
{
    protected $fillable = ['category_id', 'category_child_id', 'name_ar', 'name_en', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categoryChild(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'category_child_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * The titles a business in this field may pick: the child's own, its
     * root's, and the general ones (محاسب، سائق…) that fit any business.
     */
    public function scopeAvailableFor(Builder $q, ?int $categoryId, ?int $childId): Builder
    {
        return $q->where(function (Builder $w) use ($categoryId, $childId) {
            $w->where(fn ($g) => $g->whereNull('category_id')->whereNull('category_child_id'));

            if ($categoryId) {
                $w->orWhere(fn ($r) => $r->where('category_id', $categoryId)->whereNull('category_child_id'));
            }

            if ($childId) {
                $w->orWhere('category_child_id', $childId);
            }
        });
    }

    public function label(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return $primary !== '' ? $primary : ($ar !== '' ? $ar : $en);
    }
}
