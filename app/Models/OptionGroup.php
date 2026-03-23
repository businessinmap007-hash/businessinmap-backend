<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    protected $table = 'option_groups';

    protected $fillable = [
        'name_ar',
        'name_en',
        'reorder',
        'is_active',
    ];

    protected $casts = [
        'reorder'   => 'integer',
        'is_active' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(Option::class, 'group_id')
            ->orderBy('id');
    }

    public function activeOptions(): HasMany
    {
        return $this->hasMany(Option::class, 'group_id')
            ->when(method_exists(Option::query()->getModel(), 'scopeActive'), function ($q) {
                $q->active();
            })
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