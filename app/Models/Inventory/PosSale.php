<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A counter sale on the cashier. Its money is cash in the drawer, outside the app — never the wallet. */
class PosSale extends Model
{
    public const PAID = 'paid';
    public const RETURNED = 'returned';

    protected $table = 'inv_pos_sales';

    protected $guarded = ['id'];

    protected $casts = ['total' => 'float', 'paid' => 'float', 'sold_at' => 'datetime', 'returned_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(PosSaleLine::class, 'sale_id')->orderBy('id');
    }
}
