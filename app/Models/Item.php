<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';

    protected $fillable = [
        'business_id', // لو موجود
        'category_id', // لو موجود
        'name_ar', 'name_en',
        'description_ar', 'description_en',
        'image',
        'base_price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function variants()
    {
        return $this->hasMany(Variant::class, 'item_id');
    }

    public function extras()
    {
        return $this->hasMany(Extra::class, 'item_id');
    }
}
