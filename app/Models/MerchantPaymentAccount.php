<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's own payment-gateway sub-account (Fawry today). When the
 * sub-merchant feature is enabled, a customer's payment for this business is
 * routed to these credentials so the money lands in the merchant's Fawry
 * account, not the platform's. The `security_key` is encrypted at rest via the
 * `encrypted` cast — reads return plaintext, the column stores ciphertext.
 *
 * Read/write goes through App\Services\Payments\MerchantPaymentAccountService.
 */
class MerchantPaymentAccount extends Model
{
    protected $table = 'merchant_payment_accounts';

    protected $fillable = [
        'business_id',
        'gateway',
        'merchant_code',
        'security_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'security_key' => 'encrypted',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
