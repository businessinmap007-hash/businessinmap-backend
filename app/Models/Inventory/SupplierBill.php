<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends Model
{
    protected $table = 'inv_supplier_bills';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float', 'bill_date' => 'date'];

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierBillLine::class, 'bill_id')->orderBy('id');
    }
}
