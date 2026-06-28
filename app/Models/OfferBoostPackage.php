<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferBoostPackage extends Model
{
    protected $table = 'offer_boost_packages';

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'price',
        'currency',
        'duration_days',
        'boost_score',
        'is_featured',
        'is_active',
        'rules',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'boost_score' => 'decimal:4',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'rules' => 'array',
        'meta' => 'array',
    ];

    public function purchases()
    {
        return $this->hasMany(OfferBoostPurchase::class, 'package_id');
    }

    public function displayName(): string
    {
        return $this->name_ar ?: ($this->name_en ?: $this->key);
    }
}
