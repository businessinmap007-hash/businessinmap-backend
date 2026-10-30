<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

/** Table `crm_payments` — a credit payment that counts only once both sides confirm it (see PaymentMatching). */
class Payment extends Model
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const MISMATCH = 'mismatch';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    public const CASH = 'cash';
    public const METHODS = ['cash', 'wallet', 'instapay', 'bank'];

    public const BY_BUSINESS = 'business';
    public const BY_CUSTOMER = 'customer';

    public const MAX_ATTEMPTS = 3;

    protected $table = 'crm_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
        'attempts' => 'integer',
        'business_only' => 'boolean',
        'business_confirmed_at' => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function isTransfer(): bool
    {
        return $this->method !== self::CASH;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
