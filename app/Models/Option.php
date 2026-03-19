<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Option extends Model
{
    protected $table = 'options';

    protected $fillable = [
        'name_ar',
        'name_en',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_option', 'option_id', 'category_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        if (Schema::hasColumn('options', 'sort_order')) {
            return $query->orderByRaw('COALESCE(sort_order, 999999) ASC')->orderBy('id');
        }

        return $query->orderBy('name_ar')->orderBy('id');
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: $this->name_en ?: ('Option #' . $this->id));
    }
}