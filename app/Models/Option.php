<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Option extends Model
{
    protected $table = 'options';

    protected $fillable = [
        'group_id',
        'name_ar',
        'name_en',
    ];

    protected $casts = [
        'group_id' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'group_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('id');
    }

    public function scopeActive($query)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'is_active')) {
            return $query->where('is_active', 1);
        }

        return $query;
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: ('Option #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: ('Option #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}