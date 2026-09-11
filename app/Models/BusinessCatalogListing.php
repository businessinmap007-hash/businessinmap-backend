<?php

namespace App\Models;

use App\Models\Concerns\RecordsPriceHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business's retail listing of a shared-catalog product: (business + master
 * product + price + stock). The retail side of the offering layer (Phase 3c).
 */
class BusinessCatalogListing extends Model
{
    use RecordsPriceHistory;

    /**
     * Every move of this number is remembered — {@see RecordsPriceHistory}.
     *
     * A discount offer is checked against what the row used to cost, and that
     * check is worth nothing unless it is complete: several screens write this
     * price, so the recording lives on the model rather than in whichever of
     * them somebody remembered.
     */
    protected string $priceHistoryColumn = 'price';

    protected string $priceHistoryBusinessColumn = 'business_id';

    protected $table = 'business_catalog_listings';

    protected $fillable = [
        'business_id',
        'catalog_product_id',
        'sku',
        'price',
        'cost_price',
        'currency',
        'stock',
        'min_order_qty',
        'max_order_qty',
        'unit',
        'is_active',
        'visibility',
        'source_listing_id',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'catalog_product_id' => 'integer',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'min_order_qty' => 'integer',
        'max_order_qty' => 'integer',
        'source_listing_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /** Who may see this listing, when it is `restricted`. */
    public function audiences(): HasMany
    {
        return $this->hasMany(CatalogListingAudience::class, 'business_catalog_listing_id');
    }

    /**
     * The listing this one was bought from, when a company is reselling.
     *
     * Null is the normal case — a shop listing its own stock bought nothing
     * from anybody here.
     */
    public function sourceListing(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_listing_id');
    }

    /** True when this row is addressed to named buyers rather than the shelf. */
    public function isRestricted(): bool
    {
        return (string) $this->visibility === \App\Services\Retail\RetailListingVisibility::RESTRICTED;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    /**
     * A retail listing has no name of its own — it sells the shared catalog
     * product, so that's what an order line snapshots. See
     * OrderItem::booted(), which calls this at order-creation time.
     */
    public function offeringLabel(?string $fallback = null): string
    {
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first(['name_ar', 'name_en']);

        if (! $product) {
            return (string) ($fallback ?? '');
        }

        return app()->getLocale() === 'en'
            ? (string) ($product->name_en ?: $product->name_ar)
            : (string) ($product->name_ar ?: $product->name_en);
    }
}
