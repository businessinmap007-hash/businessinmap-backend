<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The one platform-wide setting this feature has: how many admins must
 * approve before a chat unlocks without the parties' own consent. Read/write
 * through ThreadAccessGateService, not this model directly.
 */
class ChatAccessSetting extends Model
{
    protected $fillable = [
        'admin_quorum',
    ];

    protected $casts = [
        'admin_quorum' => 'integer',
    ];
}
