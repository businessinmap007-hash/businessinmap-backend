<?php

namespace App\Models;

use App\Support\Concerns\HasLocalizedFields;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemExtra extends Model
{
    use HasLocalizedFields;

    protected $table = 'menu_item_extras';

    protected $fillable = [
        'menu_item_id',
        'group_key',
        'name_ar',
        'name_en',
        'price',
        'max_qty',
        'is_active',
    ];

    protected $casts = [
        'menu_item_id' => 'integer',
        'price' => 'decimal:2',
        'max_qty' => 'integer',
        'is_active' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Extra #' . $this->id)));
    }
}
