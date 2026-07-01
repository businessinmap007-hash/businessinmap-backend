<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreItemImage extends Model
{
    protected $table = 'business_catalog_product_images';

    protected $fillable = ['business_catalog_product_id','image_path','image_type','is_primary','sort_order'];
}
