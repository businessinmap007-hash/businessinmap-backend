<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

/** Table `crm_customers` — one customer's file at one business; see the customer files migration (2026_11_25). */
class Customer extends Model
{
    protected $table = 'crm_customers';

    protected $guarded = ['id'];

    protected $casts = [
        'tags' => 'array',
        'credit_limit' => 'float',
        'balance' => 'float',
        'payment_terms_days' => 'integer',
        'last_reminded_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    public function onApp(): bool
    {
        return $this->user_id !== null;
    }
}
