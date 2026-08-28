<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuaranteeLoyaltyGrant extends Model
{
    protected $table = 'guarantee_loyalty_grants';

    protected $fillable = [
        'user_id',
        'guarantee_level_id',
        'discount_given',
        'exhausted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'guarantee_level_id' => 'integer',
        'discount_given' => 'decimal:2',
        'exhausted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLevel::class, 'guarantee_level_id');
    }
}
