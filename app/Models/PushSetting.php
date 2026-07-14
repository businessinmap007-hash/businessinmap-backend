<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store for runtime-editable push-notification credentials (Firebase).
 * Read/write goes through App\Services\Notifications\PushSettingsService, which
 * owns the encryption of secret values — this model stays a thin row.
 */
class PushSetting extends Model
{
    protected $table = 'push_settings';

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];
}
