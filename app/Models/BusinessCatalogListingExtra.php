<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One priced add-on on a retail listing — see the create migration. */
class BusinessCatalogListingExtra extends Model
{
    public const SELECTION_SINGLE = 'single';
    public const SELECTION_MULTIPLE = 'multiple';

    protected $fillable = [
        'listing_id',
        'name_ar',
        'name_en',
        'price',
        'group_name_ar',
        'selection_type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'listing_id' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(BusinessCatalogListing::class, 'listing_id');
    }

    public function displayName(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? ($en ?: $ar) : ($ar ?: $en);

        return $primary !== '' ? $primary : ('Extra #' . $this->id);
    }
}
