<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\ServiceExecutionEngine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pins what a booking's financial readiness says — guarantee + wallet combined, what is short and how it can be closed —
 * so the deposit/guarantee logic can be lifted into the shared core (App\Services\Deposits\DepositCore) without a
 * single number moving. Rolls back.
 */
class FinancialPreviewCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceExecutionEngine $engine;

    private Booking $booking;

    private User $business;

    private int $clientId;

    private int $businessId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ServiceExecutionEngine::class);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $business = $booking?->business;

        if (! $booking || ! $business || (string) $business->type !== User::TYPE_BUSINESS) {
            $this->markTestSkipped('Needs a booking whose business is a business account.');
        }

        $this->booking = $booking;
        $this->business = $business;
        $this->clientId = (int) $booking->user_id;
        $this->businessId = (int) $business->id;

        $serviceId = (int) $booking->service_id;
        $childId = (int) ($business->category_child_id ?? 0);

        BusinessServicePrice::query()->where('business_id', $this->businessId)->where('service_id', $serviceId)->where('child_id', $childId)->delete();
        BusinessServicePrice::create([
            'business_id' => $this->businessId, 'service_id' => $serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        UserGuarantee::query()->where('user_id', $this->clientId)->where('target_type', 'client')->delete();
        UserGuarantee::query()->where('user_id', $this->businessId)->where('target_type', 'business')->delete();

        foreach ([$this->clientId, $this->businessId] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }
        DB::table('category_child_service_fees')->where('category_id', (int) $business->category_id)->where('child_id', $childId)->delete();

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $meta['pricing'] = ['final_price' => 1000];
        unset($meta['_execution_fee'], $meta['_financial_guard'], $meta['_external_deposit']);
        $booking->meta = $meta;
        $booking->price = 1000;
        $booking->status = Booking::STATUS_ACCEPTED;
        $booking->save();

        $this->wallets(1000, 1000);
    }

    private function wallets(float $client, float $business): void
    {
        $svc = app(WalletService::class);
        $svc->getOrCreateWallet($this->clientId)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $client, 'locked_balance' => 0]);
        $svc->getOrCreateWallet($this->businessId)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $business, 'locked_balance' => 0]);
    }

    /** 10% of 1000 = 100, with the business freezing $counter% of it against the customer's. */
    private function policy(float $counter = 0): void
    {
        BusinessDepositPolicy::query()->where('business_id', $this->businessId)->delete();
        DB::table('business_deposit_policies')->insert([
            'business_id' => $this->businessId,
            'platform_service_id' => (int) $this->booking->service_id,
            'category_child_id' => (int) ($this->business->category_child_id ?? 0),
            'scope_key' => BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 0,
            'is_enabled' => 1,
            'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 10,
            'max_deposit_percent' => 20,
            'wallet_hold_enabled' => 1,
            'business_counter_hold_enabled' => $counter > 0 ? 1 : 0,
            'business_counter_hold_percent' => $counter,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function guarantee(int $userId, string $target, float $coverage): void
    {
        $levelId = (int) DB::table('guarantee_levels')->value('id');

        if ($levelId <= 0) {
            $this->markTestSkipped('Needs a guarantee level.');
        }

        UserGuarantee::create([
            'user_id' => $userId,
            'target_type' => $target,
            'purchased_level_id' => $levelId,
            'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'current_coverage_amount' => $coverage,
            'used_coverage_amount' => 0,
        ]);
    }

    private function preview(): array
    {
        return $this->engine->financialPreview($this->booking->fresh());
    }

    public function test_a_wallet_deposit_with_enough_balance_is_ready_with_nothing_missing(): void
    {
        $this->policy();

        $p = $this->preview();

        $this->assertTrue($p['ok']);
        $this->assertTrue($p['deposit']['required']);
        $this->assertEqualsWithDelta(100.0, $p['deposit']['client_required'], 0.001);
        $this->assertEqualsWithDelta(0.0, $p['deposit']['business_required'], 0.001);
        $this->assertEqualsWithDelta(0.0, $p['deposit']['guarantee_applied'], 0.001);
        $this->assertEqualsWithDelta(100.0, $p['deposit']['client_wallet_required'], 0.001);
        $this->assertFalse($p['deposit']['client_guarantee_covered']);
        $this->assertTrue($p['client']['ready']);
        $this->assertEqualsWithDelta(100.0, $p['client']['required_total'], 0.001);
        $this->assertSame([], $p['client']['options']);
        $this->assertSame([], $p['messages']);
    }

    public function test_a_short_wallet_says_what_is_missing_and_how_to_close_the_gap(): void
    {
        $this->policy();
        $this->wallets(30, 1000);

        $p = $this->preview();

        $this->assertFalse($p['ok']);
        $this->assertFalse($p['client']['ready']);
        $this->assertCount(1, $p['messages']);
        $this->assertCount(1, $p['messages_by_side']['client']);
        $this->assertSame([], $p['messages_by_side']['business']);
        $this->assertEqualsWithDelta(70.0, $p['client']['options']['add_balance']['amount'], 0.001);
        $this->assertTrue($p['client']['options']['upgrade_guarantee']);
        $this->assertEqualsWithDelta(100.0, $p['client']['options']['request_friend_guarantee']['needed_coverage'], 0.001);
    }

    public function test_a_guarantee_and_the_wallet_combine_and_only_the_remainder_is_held(): void
    {
        $this->policy();
        $this->guarantee($this->clientId, GuaranteeLevel::TARGET_CLIENT, 60);

        $p = $this->preview();

        $this->assertTrue($p['ok']);
        $this->assertEqualsWithDelta(60.0, $p['deposit']['combined_guarantee_coverage'], 0.001);
        $this->assertEqualsWithDelta(60.0, $p['deposit']['guarantee_applied'], 0.001);
        $this->assertEqualsWithDelta(40.0, $p['deposit']['client_wallet_required'], 0.001);
        $this->assertFalse($p['deposit']['client_guarantee_covered']);
        $this->assertEqualsWithDelta(40.0, $p['client']['required_total'], 0.001);
    }

    public function test_a_guarantee_that_covers_it_all_needs_no_wallet_hold(): void
    {
        $this->policy();
        $this->guarantee($this->clientId, GuaranteeLevel::TARGET_CLIENT, 100);
        $this->wallets(0, 1000);

        $p = $this->preview();

        $this->assertTrue($p['ok']);
        $this->assertTrue($p['deposit']['client_guarantee_covered']);
        $this->assertEqualsWithDelta(0.0, $p['deposit']['client_wallet_required'], 0.001);
        $this->assertEqualsWithDelta(100.0, $p['deposit']['guarantee_applied'], 0.001);
        $this->assertTrue($p['client']['ready']);
        $this->assertSame([], $p['client']['options']);
    }

    public function test_a_part_guarantee_with_a_short_wallet_names_both_gaps(): void
    {
        $this->policy();
        $this->guarantee($this->clientId, GuaranteeLevel::TARGET_CLIENT, 60);
        $this->wallets(10, 1000);

        $p = $this->preview();

        $this->assertFalse($p['ok']);
        $this->assertEqualsWithDelta(40.0, $p['client']['required_total'], 0.001);
        $this->assertEqualsWithDelta(30.0, $p['client']['options']['add_balance']['amount'], 0.001);
        $this->assertEqualsWithDelta(40.0, $p['client']['options']['request_friend_guarantee']['needed_coverage'], 0.001);
    }

    public function test_the_business_counter_hold_is_judged_on_the_business_side_alone(): void
    {
        $this->policy(50);
        $this->wallets(1000, 10);

        $p = $this->preview();

        $this->assertFalse($p['ok']);
        $this->assertEqualsWithDelta(50.0, $p['deposit']['business_required'], 0.001);
        $this->assertEqualsWithDelta(50.0, $p['deposit']['business_wallet_required'], 0.001);
        $this->assertFalse($p['business']['ready']);
        $this->assertTrue($p['client']['ready']);
        $this->assertCount(1, $p['messages_by_side']['business']);
        $this->assertSame([], $p['messages_by_side']['client']);
    }

    public function test_the_business_share_is_frozen_from_its_own_guarantee_first(): void
    {
        $this->policy(50);
        $this->guarantee($this->businessId, GuaranteeLevel::TARGET_BUSINESS, 500);
        User::query()->whereKey($this->businessId)->update(['guarantee_enabled' => 1]);
        $this->wallets(1000, 0);

        $p = $this->preview();

        // «المالك، 2026-10-20»: a business that asks for a deposit stands behind it with its own guarantee
        $this->assertEqualsWithDelta(50.0, $p['deposit']['business_required'], 0.001);
        $this->assertEqualsWithDelta(50.0, $p['deposit']['business_guarantee_applied'], 0.001);
        $this->assertEqualsWithDelta(0.0, $p['deposit']['business_wallet_required'], 0.001);
        $this->assertTrue($p['deposit']['business_guarantee_covered']);
        $this->assertTrue($p['business']['ready'], 'no wallet needed — the guarantee stands for it');
    }

    public function test_a_part_covering_business_guarantee_leaves_the_rest_to_its_wallet(): void
    {
        $this->policy(50);
        $this->guarantee($this->businessId, GuaranteeLevel::TARGET_BUSINESS, 20);
        $this->wallets(1000, 10);

        $p = $this->preview();

        $this->assertEqualsWithDelta(20.0, $p['deposit']['business_guarantee_applied'], 0.001);
        $this->assertEqualsWithDelta(30.0, $p['deposit']['business_wallet_required'], 0.001);
        $this->assertFalse($p['deposit']['business_guarantee_covered']);
        $this->assertFalse($p['business']['ready'], 'its wallet is short of the 30 left');
    }

    public function test_starting_freezes_the_business_coverage_and_holds_only_the_wallet_remainder(): void
    {
        $this->policy(50);
        $this->guarantee($this->businessId, GuaranteeLevel::TARGET_BUSINESS, 20);
        $this->wallets(1000, 100);

        $this->engine->moveBookingToInProgress($this->booking);

        $deposit = Deposit::query()->where('target_type', Booking::class)->where('target_id', $this->booking->id)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $deposit->client_amount, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $deposit->business_amount, 0.001, 'only what the guarantee did not cover');

        $g = UserGuarantee::query()->where('user_id', $this->businessId)->where('target_type', 'business')->firstOrFail();
        $this->assertEqualsWithDelta(20.0, (float) $g->used_coverage_amount, 0.001, 'its coverage is frozen for the operation');

        $business = Wallet::query()->where('user_id', $this->businessId)->firstOrFail();
        $this->assertEqualsWithDelta(70.0, (float) $business->balance, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $business->locked_balance, 0.001);
    }

    public function test_an_already_frozen_deposit_asks_for_nothing_more(): void
    {
        $this->policy();
        $this->engine->moveBookingToInProgress($this->booking);

        $p = $this->preview();

        $this->assertTrue($p['deposit']['already_frozen']);
        $this->assertEqualsWithDelta(0.0, $p['deposit']['client_required'], 0.001);
    }

    // ── a business that REQUIRES the guarantee: the covering guarantee is frozen, not left idle ────────────────

    public function test_a_required_guarantee_that_covers_is_frozen_for_the_operation_and_the_wallet_is_untouched(): void
    {
        $this->policy();
        DB::table('business_deposit_policies')->where('business_id', $this->businessId)->update([
            'client_guarantee_strategy' => BusinessDepositPolicy::GUARANTEE_GENERAL,
        ]);
        $this->guarantee($this->clientId, GuaranteeLevel::TARGET_CLIENT, 500);

        $p = $this->preview();

        $this->assertTrue($p['deposit']['required']);
        $this->assertEqualsWithDelta(100.0, $p['deposit']['client_required'], 0.001);
        $this->assertEqualsWithDelta(100.0, $p['deposit']['guarantee_applied'], 0.001);
        $this->assertEqualsWithDelta(0.0, $p['deposit']['client_wallet_required'], 0.001);

        $this->engine->moveBookingToInProgress($this->booking);

        $g = UserGuarantee::query()->where('user_id', $this->clientId)->where('target_type', 'client')->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $g->used_coverage_amount, 0.001, 'frozen on the guarantee');

        $wallet = Wallet::query()->where('user_id', $this->clientId)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $wallet->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $wallet->locked_balance, 0.001);
    }
}
