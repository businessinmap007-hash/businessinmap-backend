<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArbitrationSession extends Model
{
    protected $fillable = [
        'dispute_id',
        'arbitrator_id',
        'outcome',
        'client_percent',
        'business_percent',
        'amount_to_client',
        'amount_to_business',
        'platform_fine_amount',
        'platform_fine_on',
        'notes',
    ];

    protected $casts = [
        'client_percent' => 'decimal:2',
        'business_percent' => 'decimal:2',
        'amount_to_client' => 'decimal:2',
        'amount_to_business' => 'decimal:2',
        'platform_fine_amount' => 'decimal:2',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function arbitrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbitrator_id');
    }
}
