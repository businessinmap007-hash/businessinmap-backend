<?php

namespace App\Models;

use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of a menu item's own priced extras — «الصوص», «الإضافات» —
 * with one property a bare `group_key` string never could: whether the
 * customer picks exactly one (radio) or any number (checkbox).
 */
class MenuItemExtraGroup extends Model
{
    use HasLocalizedFields;

    protected $table = 'menu_item_extra_groups';

    public const SELECTION_SINGLE = 'single';
    public const SELECTION_MULTIPLE = 'multiple';

    public const SELECTION_TYPES = [self::SELECTION_SINGLE, self::SELECTION_MULTIPLE];

    protected $fillable = [
        'menu_item_id',
        'name_ar',
        'name_en',
        'selection_type',
        'reorder',
        'is_active',
    ];

    protected $casts = [
        'menu_item_id' => 'integer',
        'reorder' => 'integer',
        'is_active' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(MenuItemExtra::class, 'extra_group_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isSingle(): bool
    {
        return $this->selection_type === self::SELECTION_SINGLE;
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Group #' . $this->id)));
    }
}
