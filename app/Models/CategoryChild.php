<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryChild extends Model
{
    protected $table = 'category_children_master';

    protected $fillable = [
        'name_ar',
        'name_en',
        'reorder',
    ];

    protected $casts = [
        'reorder' => 'integer',
    ];

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_parent_child',
            'child_id',
            'parent_id'
        )->withTimestamps();
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            Option::class,
            'category_child_option',
            'child_id',
            'option_id'
        );
    }

    public function activeOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            Option::class,
            'category_child_option',
            'child_id',
            'option_id'
        )->where('options.is_active', 1);
    }

    public function optionLinks(): HasMany
    {
        return $this->hasMany(CategoryChildOption::class, 'child_id')
            ->orderBy('reorder')
            ->orderBy('id');
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: ('Category Child #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: ('Category Child #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}