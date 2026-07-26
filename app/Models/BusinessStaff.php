<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A delegated staff membership (see the create_business_staff migration): a user
 * granted a set of capabilities on a business account.
 */
class BusinessStaff extends Model
{
    protected $table = 'business_staff';

    protected $fillable = [
        'business_id',
        'user_id',
        'title',
        'capabilities',
        'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
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

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, (array) $this->capabilities, true);
    }
}
