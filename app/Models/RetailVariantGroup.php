<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business's own product family shown as ONE card with a variant picker -
 * "قميص كلاسيك: أزرق-M، أزرق-L، أحمر-M" - purely a discovery grouping over
 * several of the business's own {@see BusinessCatalogListing} rows. See the
 * create_retail_variant_groups migration.
 */
class RetailVariantGroup extends Model
{
    protected $fillable = [
        'business_id',
        'name_ar',
        'name_en',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(RetailVariantOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = app()->getLocale() === 'en' ? ($this->name_en ?: $this->name_ar) : $this->name_ar;

        return (string) ($name ?: ('#' . $this->id));
    }
}
