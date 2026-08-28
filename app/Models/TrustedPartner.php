<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's standing vouch for a specific user — see the migration's
 * docblock and TrustedPartnerService.
 */
class TrustedPartner extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
