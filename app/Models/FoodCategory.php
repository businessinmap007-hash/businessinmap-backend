<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A section of the food catalogue — bread and starches, legumes, dairy, meat, fruit… */
class FoodCategory extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function foods(): HasMany
    {
        return $this->hasMany(FoodItem::class);
    }

    public function label(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return $primary !== '' ? $primary : ($ar !== '' ? $ar : $en);
    }
}
