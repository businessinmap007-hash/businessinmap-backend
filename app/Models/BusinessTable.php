<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A restaurant table with a permanent QR token (BIM-13.3). Scanning it resolves
 * to the table's open shared cart (or opens a new one). See CustomerCartService.
 */
class BusinessTable extends Model
{
    protected $fillable = [
        'business_id',
        'label',
        'token',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /** A fresh, URL-safe permanent token for a table. */
    public static function newToken(): string
    {
        return Str::random(32);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'business_table_id');
    }
}
