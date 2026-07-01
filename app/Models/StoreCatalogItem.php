<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCatalogItem extends Model
{
    protected $table = 'business_catalog_products';

    protected $fillable = [
        'business_id',
        'catalog_product_id',
        'business_sku',
        'custom_name_ar',
        'custom_name_en',
        'price',
        'offer_price',
        'currency_code',
        'stock_quantity',
        'reserved_quantity',
        'stock_status',
        'is_available',
        'status',
    ];

    public function catalogProduct()
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
