<?php

namespace App\Models;

use App\Models\Concerns\RecordsPriceHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'currency',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'catalog_product_id' => 'integer',
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
