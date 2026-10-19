<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One fee actually taken for an event — the platform's record, and the clock for «one fee per change session». */
class PlatformEventCharge extends Model
{
    protected $fillable = ['event_key', 'role', 'user_id', 'reference_type', 'reference_id', 'occurrence', 'amount', 'wallet_transaction_id'];

    protected $casts = ['amount' => 'decimal:2'];
}
