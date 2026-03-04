<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $fillable = [
        'client_id',
        'business_id',
        'target_type',
        'target_id',

        'total_amount',
        'client_percent',
        'business_percent',
        'client_amount',
        'business_amount',

        'status',

        'client_confirmed',
        'business_confirmed',
        'client_outside_bim',
        'business_outside_bim',

        'released_at',
        'refunded_at',
    ];

    protected $casts = [
        // ✅ Money as decimal (no float)
        'total_amount'    => 'decimal:2',
        'client_amount'   => 'decimal:2',
        'business_amount' => 'decimal:2',

        'client_percent'   => 'integer',
        'business_percent' => 'integer',

        'client_confirmed'     => 'boolean',
        'business_confirmed'   => 'boolean',
        'client_outside_bim'   => 'boolean',
        'business_outside_bim' => 'boolean',

        'released_at' => 'datetime',
        'refunded_at' => 'datetime',

        // ✅ Enum cast (مهم)
        'status' => DepositStatus::class,
    ];

    /* =========================
     * Relations
     * ========================= */

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /* =========================
     * Helpers (اختيارية مفيدة)
     * ========================= */

    public function isFrozen(): bool
    {
        return $this->status === DepositStatus::FROZEN;
    }

    public function isReleased(): bool
    {
        return $this->status === DepositStatus::RELEASED;
    }

    public function isRefunded(): bool
    {
        return $this->status === DepositStatus::REFUNDED;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [DepositStatus::RELEASED, DepositStatus::REFUNDED], true);
    }
}
