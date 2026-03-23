<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryChildOptionGroup extends Model
{
    protected $table = 'category_child_option_groups';

    protected $fillable = [
        'child_id',
        'name_ar',
        'name_en',
        'reorder',
        'is_active',
    ];

    protected $casts = [
        'child_id'   => 'integer',
        'reorder'    => 'integer',
        'is_active'  => 'boolean',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function childOptionLinks(): HasMany
    {
        return $this->hasMany(CategoryChildOption::class, 'group_id');
    }

    public function activeChildOptionLinks(): HasMany
    {
        return $this->childOptionLinks()
            ->whereHas('option', function ($q) {
                $q->where('is_active', 1);
            })
            ->orderBy('reorder')
            ->orderBy('id');
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: ('Group #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: ('Group #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}