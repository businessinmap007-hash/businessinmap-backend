<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One color/size choice inside a {@see RetailVariantGroup} — points at one of the business's own listings. */
class RetailVariantOption extends Model
{
    protected $fillable = [
        'retail_variant_group_id',
        'business_catalog_listing_id',
        'label_ar',
        'label_en',
        'sort_order',
    ];

    protected $casts = [
        'retail_variant_group_id' => 'integer',
        'business_catalog_listing_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(RetailVariantGroup::class, 'retail_variant_group_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(BusinessCatalogListing::class, 'business_catalog_listing_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        $label = app()->getLocale() === 'en' ? ($this->label_en ?: $this->label_ar) : $this->label_ar;

        return (string) ($label ?: ('#' . $this->id));
    }
}
