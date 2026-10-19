<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What the platform charges one party of one event — see the create_platform_event_fees migration. */
class PlatformEventFee extends Model
{
    public const CALC_FIXED = 'fixed';
    public const CALC_PERCENT = 'percent';

    protected $fillable = ['event_key', 'role', 'enabled', 'calc_type', 'amount', 'min_amount', 'max_amount', 'currency'];

    protected $casts = [
        'enabled' => 'boolean',
        'amount' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
    ];

    /** The fee for a base amount: a fixed sum, or a percent of the base, kept between the floor and the ceiling. */
    public function feeFor(float $base): float
    {
        if (! $this->enabled) {
            return 0.0;
        }

        $fee = $this->calc_type === self::CALC_PERCENT ? $base * (float) $this->amount / 100 : (float) $this->amount;

        if ($this->min_amount !== null) {
            $fee = max($fee, (float) $this->min_amount);
        }

        if ($this->max_amount !== null && (float) $this->max_amount > 0) {
            $fee = min($fee, (float) $this->max_amount);
        }

        return round(max($fee, 0.0), 2);
    }
}
