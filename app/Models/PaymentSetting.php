<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store for runtime-editable payment gateway credentials. Read/write
 * is done through App\Services\Payments\PaymentSettingsService, which owns the
 * encryption of secret values — this model stays a thin row.
 */
class PaymentSetting extends Model
{
    protected $table = 'payment_settings';

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];
}
