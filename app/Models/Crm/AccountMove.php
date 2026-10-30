<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

/** Table `crm_account_moves` — one line of a customer's credit book. Append-only: corrected by an opposite line. */
class AccountMove extends Model
{
    public const OPENING = 'opening';
    public const SALE = 'sale';
    public const ORDER = 'order';
    public const PAYMENT = 'payment';
    public const RETURN = 'return';
    public const ADJUSTMENT = 'adjustment';

    protected $table = 'crm_account_moves';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float', 'occurred_at' => 'datetime'];
}
