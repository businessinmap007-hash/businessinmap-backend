<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section (قائمة) in the Taxonomy Lab. Nests via parent_id; holds items that
 * reference atoms from either sandbox source. Sandbox-only — never live data.
 */
class LabList extends Model
{
    protected $fillable = ['parent_id', 'key', 'name_ar', 'name_en', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabListItem::class, 'list_id')->orderBy('sort_order')->orderBy('id');
    }

    public function displayName(string $locale = 'ar'): string
    {
        return $locale === 'en'
            ? ((string) ($this->name_en ?: $this->name_ar))
            : ((string) ($this->name_ar ?: $this->name_en));
    }
}
