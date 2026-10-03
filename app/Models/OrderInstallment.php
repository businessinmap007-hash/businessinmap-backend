<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One monthly payment of an order bought on instalments — see App\Services\InstallmentPlan. */
class OrderInstallment extends Model
{
    protected $fillable = ['order_id', 'seq', 'due_on', 'amount', 'paid_at'];

    protected $casts = [
        'seq' => 'integer',
        'due_on' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
