<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
        'booking_id','bookable_item_id','platform_service_id',
        'category_id','category_child_id',
        'mode','calculation_base','deposit_type',
        'deposit_value','deposit_percent_used',
        'deposit_base_amount','deposit_amount',
        'wallet_hold_required','wallet_hold_amount',
        'wallet_hold_status','client_wallet_transaction_id',
        'business_counter_hold_required',
        'business_counter_hold_percent','business_counter_hold_amount',
        'business_counter_hold_status','business_wallet_transaction_id',
        'external_deposit_required','external_deposit_amount',
        'external_deposit_status','external_reference','external_paid_at',
        'external_verified_at','external_verified_by','external_proof_path',
        'external_notes','affects_remaining_amount',
        'remaining_amount_before_external',
        'remaining_amount_after_external','policy_snapshot',
    ];

    protected $casts = [
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
        'booking_id' => 'integer',
        'bookable_item_id' => 'integer',
        'platform_service_id' => 'integer',
        'category_id' => 'integer',
        'category_child_id' => 'integer',
        'deposit_value' => 'decimal:2',
        'deposit_percent_used' => 'decimal:2',
        'deposit_base_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'wallet_hold_required' => 'boolean',
        'wallet_hold_amount' => 'decimal:2',
        'client_wallet_transaction_id' => 'integer',
        'business_counter_hold_required' => 'boolean',
        'business_counter_hold_percent' => 'decimal:2',
        'business_counter_hold_amount' => 'decimal:2',
        'business_wallet_transaction_id' => 'integer',
        'external_deposit_required' => 'boolean',
        'external_deposit_amount' => 'decimal:2',
        'external_paid_at' => 'datetime',
        'external_verified_at' => 'datetime',
        'external_verified_by' => 'integer',
        'affects_remaining_amount' => 'boolean',
        'remaining_amount_before_external' => 'decimal:2',
        'remaining_amount_after_external' => 'decimal:2',
        'policy_snapshot' => 'array',

        'status' => DepositStatus::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'reference_id', 'id')
            ->where('reference_type', 'deposit');
    }

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

    public function isSplit(): bool
    {
        return $this->status === DepositStatus::SPLIT;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            DepositStatus::RELEASED,
            DepositStatus::REFUNDED,
            DepositStatus::SPLIT,
        ], true);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DepositEvent::class, 'deposit_id')->orderByDesc('id');
    }
}