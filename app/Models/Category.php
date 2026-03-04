<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'per_month',
        'per_year',
        'image',
        'reorder',
        'is_active',
        'name_ar',
        'name_en',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'per_month' => 'float',
        'per_year'  => 'float',
        'reorder'   => 'integer',
    ];

    // scope root
    public function scopeParentCategory(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
    }

    public function scopeActive(Builder $query, $value = 1): Builder
    {
        if ($value === null || $value === '') return $query;
        return $query->where('is_active', (int)$value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(reorder, 999999) ASC')
                     ->orderByDesc('id');
    }

    // Relations
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function options()
    {
        return $this->belongsToMany(Option::class, 'category_option');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // users that belong to this category (users.category_id)
    public function users()
    {
        return $this->hasMany(User::class, 'category_id');
    }

    // helper name
    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string)($this->name_ar ?: $this->name_en ?: '');
        }

        return (string)($this->name_en ?: $this->name_ar ?: '');
    }
}
