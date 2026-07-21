<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A platform fine levied on a user for fraud/abuse, outside a dispute.
 *
 * Lifecycle: frozen → (appealed → overturned | upheld) → collected, or
 * cancelled. The money is locked at levy and only leaves the wallet on
 * collection, so a fine that is overturned or cancelled costs the user nothing.
 */
class Fine extends Model
{
    public const STATUS_FROZEN = 'frozen';         // levied, amount locked, window open
    public const STATUS_APPEALED = 'appealed';     // user contested, awaiting admin
    public const STATUS_OVERTURNED = 'overturned'; // appeal accepted — unlocked, terminal
    public const STATUS_UPHELD = 'upheld';         // appeal rejected — due for collection
    public const STATUS_COLLECTED = 'collected';   // captured to treasury, terminal
    public const STATUS_CANCELLED = 'cancelled';   // admin withdrew it, terminal

    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_FRAUD = 'fraud';
    public const SOURCE_SETTLEMENT = 'settlement';

    protected $fillable = [
        'user_id', 'amount', 'frozen_amount', 'collected_amount', 'reason', 'source',
        'status', 'is_appealable', 'appeal_deadline_at', 'levied_by',
        'frozen_at', 'collected_at', 'resolved_at', 'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'frozen_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'is_appealable' => 'boolean',
        'appeal_deadline_at' => 'datetime',
        'frozen_at' => 'datetime',
        'collected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'meta' => 'array',
    ];

    /** Arabic status labels for the admin panel (Arabic-first, like the menu). */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_FROZEN => 'مجمّدة (نافذة اعتراض)',
            self::STATUS_APPEALED => 'قيد الاعتراض',
            self::STATUS_UPHELD => 'مؤيَّدة (مستحقة الخصم)',
            self::STATUS_OVERTURNED => 'ملغاة باعتراض',
            self::STATUS_COLLECTED => 'محصَّلة',
            self::STATUS_CANCELLED => 'ملغاة',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function appeals()
    {
        return $this->hasMany(FineAppeal::class);
    }

    /** How much of the fine still isn't locked in the wallet. */
    public function shortfall(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->frozen_amount), 2);
    }

    /** Fully backed by a wallet hold. */
    public function isFullyFrozen(): bool
    {
        return $this->shortfall() <= 0;
    }

    /** Still moving — not overturned, collected or cancelled. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_FROZEN, self::STATUS_APPEALED, self::STATUS_UPHELD], true);
    }

    /** The user may still contest it. */
    public function appealWindowOpen(): bool
    {
        return $this->status === self::STATUS_FROZEN
            && $this->is_appealable
            && $this->appeal_deadline_at !== null
            && $this->appeal_deadline_at->isFuture();
    }

    /**
     * Ready to be captured to the treasury: either an admin upheld it, or the
     * appeal window closed with no appeal (or it was never appealable). Never
     * while an appeal is still pending a decision.
     */
    public function isCollectable(): bool
    {
        if ($this->status === self::STATUS_UPHELD) {
            return true;
        }

        if ($this->status !== self::STATUS_FROZEN) {
            return false;
        }

        return ! $this->is_appealable
            || $this->appeal_deadline_at === null
            || ! $this->appeal_deadline_at->isFuture();
    }
}
