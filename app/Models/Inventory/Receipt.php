<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    protected $table = 'inv_receipts';

    protected $guarded = ['id'];

    protected $casts = ['received_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class, 'receipt_id')->orderBy('id');
    }
}
