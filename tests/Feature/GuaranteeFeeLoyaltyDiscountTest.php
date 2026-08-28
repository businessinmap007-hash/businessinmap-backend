<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\GuaranteeLevel;
use App\Models\GuaranteeLoyaltyGrant;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\WalletFeeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 2026-08-28: a one-time loyalty perk per guarantee level — while a user
 * holds an active level that carries a fee_discount_amount/percent, every
 * platform fee they pay is shaved down until the running total shaved
 * reaches the level's own price (required_locked_amount), then it stops for
 * THAT level permanently. Upgrading to a new level grants a fresh allowance.
 * See WalletFeeService::applyLoyaltyDiscount.
 */
class GuaranteeFeeLoyaltyDiscountTest extends TestCase
{
    use DatabaseTransactions;

    private WalletFeeService $fees;

    private Booking $booking;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fees = app(WalletFeeService::class);

        $booking = Booking::withTrashed()->whereNotNull('user_id')->whereNotNull('business_id')->first();
        if ($booking && $booking->trashed()) {
            $booking->restore();
        }
        if (! $booking) {
            $this->markTestSkipped('Needs a booking.');
        }
        $this->booking = $booking;
        $this->userId = (int) $booking->user_id;

        $wallet = app(WalletService::class)->getOrCreateWallet($this->userId);
        $wallet->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);

        DB::table('user_service_fee_consents')->updateOrInsert(
            ['user_id' => $this->userId],
            ['fee_auto_charge_enabled' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function makeLevel(array $overrides = []): GuaranteeLevel
    {
        return GuaranteeLevel::create(array_merge([
            'code' => 'loyalty_test_' . uniqid(),
            'name_ar' => 'مستوى اختبار',
            'name_en' => 'Test Level',
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'required_locked_amount' => 5,
            'pending_coverage_amount' => 0,
            'active_coverage_amount' => 0,
            'required_completed_operations' => 0,
            'required_trust_score' => 0,
            'priority' => 0,
            'is_active' => true,
            'fee_discount_amount' => 1,
        ], $overrides));
    }

    private function giveGuarantee(GuaranteeLevel $level): UserGuarantee
    {
        return UserGuarantee::query()->updateOrCreate(
            ['user_id' => $this->userId, 'target_type' => GuaranteeLevel::TARGET_CLIENT],
            [
                'purchased_level_id' => $level->id,
                'effective_level_id' => $level->id,
                'status' => UserGuarantee::STATUS_ACTIVE,
                'locked_amount' => 0,
                'current_coverage_amount' => 0,
                'used_coverage_amount' => 0,
            ]
        );
    }

    private function charge(float $amount, string $feeCode): float
    {
        $m = new ReflectionMethod(WalletFeeService::class, 'createWalletFeeTransaction');
        $m->setAccessible(true);

        $tx = $m->invoke(
            $this->fees,
            $this->booking,
            $feeCode,
            CategoryChildServiceFee::PAYER_CLIENT,
            $this->userId,
            $amount,
            ['amount' => $amount]
        );

        return (float) $tx->amount;
    }

    public function test_a_fee_is_shaved_by_the_configured_fixed_amount(): void
    {
        $level = $this->makeLevel();
        $this->giveGuarantee($level);

        $charged = $this->charge(3, 'loyalty_' . uniqid());

        $this->assertSame(2.0, $charged, 'a 3 EGP fee minus a 1 EGP loyalty discount must charge 2');
        $this->assertDatabaseHas('guarantee_loyalty_grants', [
            'user_id' => $this->userId,
            'guarantee_level_id' => $level->id,
            'discount_given' => 1.00,
        ]);
    }

    public function test_the_discount_stops_once_the_cap_is_reached_and_never_resumes_on_this_level(): void
    {
        $level = $this->makeLevel(['required_locked_amount' => 5, 'fee_discount_amount' => 1]);
        $this->giveGuarantee($level);

        $charges = [];
        for ($i = 1; $i <= 6; $i++) {
            $charges[] = $this->charge(3, 'loyalty_cap_' . $i . '_' . uniqid());
        }

        // First 5 operations each get the 1 EGP discount (5 x 1 = the level's price).
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(2.0, $charges[$i], "operation #" . ($i + 1) . " should still be discounted");
        }

        // The 6th is charged in full — the allowance for THIS level is spent.
        $this->assertSame(3.0, $charges[5], 'the 6th operation must be charged in full once the cap is spent');

        $this->assertDatabaseHas('guarantee_loyalty_grants', [
            'user_id' => $this->userId,
            'guarantee_level_id' => $level->id,
            'discount_given' => 5.00,
        ]);
        $this->assertNotNull(
            GuaranteeLoyaltyGrant::query()->where('user_id', $this->userId)->where('guarantee_level_id', $level->id)->value('exhausted_at')
        );
    }

    public function test_upgrading_to_a_new_level_grants_a_fresh_allowance(): void
    {
        $silver = $this->makeLevel(['code' => 'loyalty_silver_' . uniqid(), 'required_locked_amount' => 2, 'fee_discount_amount' => 1]);
        $this->giveGuarantee($silver);

        // Spend the silver allowance out (2 EGP cap / 1 EGP per op = 2 ops).
        $this->charge(3, 'loyalty_up_1_' . uniqid());
        $this->charge(3, 'loyalty_up_2_' . uniqid());
        $exhaustedCharge = $this->charge(3, 'loyalty_up_3_' . uniqid());
        $this->assertSame(3.0, $exhaustedCharge, 'silver allowance must already be spent');

        $gold = $this->makeLevel(['code' => 'loyalty_gold_' . uniqid(), 'required_locked_amount' => 5, 'fee_discount_amount' => 1]);
        $this->giveGuarantee($gold);

        $afterUpgrade = $this->charge(3, 'loyalty_up_4_' . uniqid());

        $this->assertSame(2.0, $afterUpgrade, 'upgrading to gold must grant a brand-new one-time allowance');
    }

    public function test_a_percent_discount_is_computed_off_the_fee_amount(): void
    {
        $level = $this->makeLevel(['fee_discount_amount' => null, 'fee_discount_percent' => 20, 'required_locked_amount' => 100]);
        $this->giveGuarantee($level);

        $charged = $this->charge(10, 'loyalty_percent_' . uniqid());

        $this->assertSame(8.0, $charged, '20% off a 10 EGP fee must charge 8');
    }

    public function test_no_guarantee_means_no_discount(): void
    {
        $charged = $this->charge(3, 'loyalty_none_' . uniqid());

        $this->assertSame(3.0, $charged);
    }

    public function test_a_level_with_no_discount_configured_charges_in_full(): void
    {
        $level = $this->makeLevel(['fee_discount_amount' => null, 'fee_discount_percent' => null]);
        $this->giveGuarantee($level);

        $charged = $this->charge(3, 'loyalty_unconfigured_' . uniqid());

        $this->assertSame(3.0, $charged);
    }
}
