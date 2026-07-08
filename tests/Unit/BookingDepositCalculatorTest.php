<?php

namespace Tests\Unit;

use App\Models\BusinessDepositPolicy as P;
use App\Services\BookingDepositCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Pure-computation guards for the deposit calculator (no DB). Locks the money
 * math: percent-of-base, the system 20% cap, min/max clamps, fixed base, and
 * the wallet-hold amount.
 */
class BookingDepositCalculatorTest extends TestCase
{
    private function calc(array $policy, array $amounts = ['total_amount' => 1000]): array
    {
        return (new BookingDepositCalculator())->calculate($policy, $amounts);
    }

    private function basePolicy(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'calculation_base' => P::BASE_TOTAL,
            'deposit_type' => P::TYPE_PERCENT,
            'deposit_value' => 10.0,
            'max_deposit_percent' => 20.0,
            'deposit_mode' => P::MODE_WALLET_HOLD,
            'wallet_hold_enabled' => true,
        ], $overrides);
    }

    public function test_disabled_policy_yields_no_deposit(): void
    {
        $out = $this->calc(['enabled' => false]);

        $this->assertFalse($out['enabled']);
        $this->assertFalse($out['required']);
        $this->assertSame(0.0, (float) $out['amount']);
    }

    public function test_percent_of_total(): void
    {
        $out = $this->calc($this->basePolicy(['deposit_value' => 10.0]));

        $this->assertSame(100.0, (float) $out['amount']);      // 10% of 1000
        $this->assertSame(10.0, (float) $out['configured_percent']);
    }

    public function test_percent_is_capped_at_system_max_20(): void
    {
        // Ask for 50% with a 100% policy max — must still cap at the system 20%.
        $out = $this->calc($this->basePolicy(['deposit_value' => 50.0, 'max_deposit_percent' => 100.0]));

        $this->assertSame(20.0, (float) $out['configured_percent']);
        $this->assertSame(200.0, (float) $out['amount']);      // 20% of 1000
    }

    public function test_min_deposit_amount_raises_within_cap(): void
    {
        $out = $this->calc($this->basePolicy(['deposit_value' => 5.0, 'min_deposit_amount' => 120.0]));

        // 5% = 50, raised to the 120 floor, still under the 200 cap.
        $this->assertSame(120.0, (float) $out['amount']);
    }

    public function test_max_deposit_amount_lowers_the_result(): void
    {
        $out = $this->calc($this->basePolicy(['deposit_value' => 20.0, 'max_deposit_amount' => 80.0]));

        // 20% = 200, capped down to the 80 ceiling.
        $this->assertSame(80.0, (float) $out['amount']);
    }

    public function test_fixed_base_uses_the_value_within_cap(): void
    {
        $out = $this->calc($this->basePolicy([
            'calculation_base' => P::BASE_FIXED,
            'deposit_value' => 150.0,
        ]));

        $this->assertSame(150.0, (float) $out['amount']);      // min(150, 20% of 1000 = 200)
    }

    public function test_wallet_hold_amount_matches_the_deposit(): void
    {
        $out = $this->calc($this->basePolicy(['deposit_value' => 10.0]));

        $this->assertTrue($out['wallet_hold_required']);
        $this->assertSame((float) $out['amount'], (float) $out['hold']);
    }
}
