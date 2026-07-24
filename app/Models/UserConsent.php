<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's recorded acceptance of a legal document at a version. Written once at
 * signup (LegalConsentService); the audit trail of who accepted what, and when.
 */
class UserConsent extends Model
{
    public const DOCUMENT_TERMS = 'terms';
    public const DOCUMENT_PRIVACY = 'privacy';

    protected $fillable = [
        'user_id',
        'document',
        'version',
        'accepted_at',
        'ip',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
