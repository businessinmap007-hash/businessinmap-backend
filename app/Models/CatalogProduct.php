<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A product in the shared catalog master. SoftDeletes means the default scope is
 * the deduped masters — duplicates carry a `deleted_at` and are excluded. See
 * CatalogDedupService and docs/architecture-blueprint.md (Phase 3).
 */
class CatalogProduct extends Model
{
    use SoftDeletes;

    protected $table = 'catalog_products';

    protected $guarded = ['id'];

    protected $casts = [
        'package_value' => 'decimal:3',
        'is_active' => 'boolean',
        'is_verified_egypt' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('catalog_products.is_active', 1);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . mb_strtolower($term) . '%';

        return $query->where(function (Builder $q) use ($like, $term) {
            $q->whereRaw('LOWER(catalog_products.name_ar) LIKE ?', [$like])
                ->orWhereRaw('LOWER(catalog_products.name_en) LIKE ?', [$like])
                ->orWhere('catalog_products.default_barcode', $term);
        });
    }

    public function displayName(): string
    {
        $ar = trim((string) ($this->name_ar ?? ''));
        $en = trim((string) ($this->name_en ?? ''));

        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . $this->id));
    }
}
