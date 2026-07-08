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
 * The capstone: starting a booking whose business REQUIRES a wallet-hold
 * deposit. moveBookingToInProgress must, in one transaction, pass the financial
 * guard, freeze the deposit (holding the client's funds), charge the execution
 * fee, and flip the status — tying together pricing, the deposit policy, the
 * escrow hold and the state machine. Client-only hold (business counter-hold
 * disabled); everything seeded here is rolled back.
 */
class ServiceExecutionEngineDepositFlowTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceExecutionEngine $engine;

    private Booking $booking;

    private int $clientId;

    private int $levelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ServiceExecutionEngine::class);
        $walletSvc = app(WalletService::class);

        $booking = Booking::query()
            ->whereNotNull('user_id')
            ->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        $business = $booking?->business;

        if (! $booking || ! $business || (string) $business->type !== User::TYPE_BUSINESS) {
            $this->markTestSkipped('Needs a booking whose business is a business account.');
        }

        $this->booking = $booking;
        $this->clientId = (int) $booking->user_id;

        $businessId = (int) $business->id;
        $serviceId = (int) $booking->service_id;
        $childId = (int) ($business->category_child_id ?? 0);

        // Backing price (deposit base) for this business/service/child.
        BusinessServicePrice::query()
            ->where('business_id', $businessId)->where('service_id', $serviceId)->where('child_id', $childId)
            ->delete();
        BusinessServicePrice::create([
            'business_id' => $businessId, 'service_id' => $serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        // Enabled wallet-hold policy: 10% of total, counter-hold off (client only).
        BusinessDepositPolicy::query()->where('business_id', $businessId)->delete();
        DB::table('business_deposit_policies')->insert([
            'business_id' => $businessId,
            'platform_service_id' => $serviceId,
            'category_child_id' => $childId,
            'scope_key' => BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 0,
            'is_enabled' => 1,
            'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 10,
            'max_deposit_percent' => 20,
            'wallet_hold_enabled' => 1,
            'business_counter_hold_enabled' => 0,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Clean deposit slate + no fees + no client guarantee (start from zero
        // guarantee coverage so each test controls it explicitly).
        $this->levelId = (int) DB::table('guarantee_levels')->value('id');
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        UserGuarantee::query()->where('user_id', $this->clientId)->where('target_type', 'client')->delete();
        foreach ([$this->clientId, $businessId] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Ready + confirmed booking priced at 1000. Pin the pricing meta so the
        // deposit base is deterministic (depositPolicy reads pricing.final_price
        // before the booking's price column).
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $meta['pricing'] = ['final_price' => 1000];
        unset($meta['_execution_fee'], $meta['_financial_guard']);
        $booking->meta = $meta;
        $booking->price = 1000;
        $booking->status = Booking::STATUS_ACCEPTED;
        $booking->save();

        // Fund the client so the hold can succeed.
        $w = $walletSvc->getOrCreateWallet($this->clientId);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    public function test_starting_freezes_the_deposit_and_holds_client_funds(): void
    {
        $this->engine->moveBookingToInProgress($this->booking);

        // Status advanced.
        $this->assertSame(
            Booking::STATUS_IN_PROGRESS,
            Booking::query()->whereKey($this->booking->id)->value('status')
        );

        // A frozen deposit of 10% (100) was created for the booking.
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)->where('target_id', $this->booking->id)
            ->latest('id')->first();
        $this->assertNotNull($deposit);
        $this->assertTrue($deposit->isFrozen());
        $this->assertEqualsWithDelta(100.0, (float) $deposit->client_amount, 0.001);

        // The client's funds moved from available into locked.
        $w = Wallet::query()->where('user_id', $this->clientId)->first();
        $this->assertEqualsWithDelta(900.0, (float) $w->balance, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $w->locked_balance, 0.001);
    }

    /** Give the client a self-owned, active guarantee with the given coverage. */
    private function seedClientGuarantee(float $coverage): void
    {
        if ($this->levelId <= 0) {
            $this->markTestSkipped('Needs a guarantee level.');
        }

        UserGuarantee::create([
            'user_id' => $this->clientId,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $this->levelId,
            'effective_level_id' => $this->levelId,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'current_coverage_amount' => $coverage,
            'used_coverage_amount' => 0,
        ]);
    }

    public function test_guarantee_and_wallet_combine_partially_to_cover_the_deposit(): void
    {
        // Deposit is 100. Guarantee covers 60; the wallet holds only the 40 remainder.
        $this->seedClientGuarantee(60);

        $this->engine->moveBookingToInProgress($this->booking);

        $this->assertSame(
            Booking::STATUS_IN_PROGRESS,
            Booking::query()->whereKey($this->booking->id)->value('status')
        );

        $deposit = Deposit::query()
            ->where('target_type', Booking::class)->where('target_id', $this->booking->id)
            ->latest('id')->first();
        $this->assertNotNull($deposit);
        $this->assertEqualsWithDelta(40.0, (float) $deposit->client_amount, 0.001, 'wallet holds only the remainder');

        $w = Wallet::query()->where('user_id', $this->clientId)->first();
        $this->assertEqualsWithDelta(960.0, (float) $w->balance, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $w->locked_balance, 0.001);

        // The applied guarantee coverage (60) is frozen on the client's own guarantee.
        $g = UserGuarantee::query()->where('user_id', $this->clientId)->where('target_type', 'client')->first();
        $this->assertEqualsWithDelta(60.0, (float) $g->used_coverage_amount, 0.001, 'self guarantee coverage frozen');
        $this->assertEqualsWithDelta(0.0, $g->availableCoverage(), 0.001);
    }

    public function test_full_guarantee_coverage_needs_no_wallet_hold(): void
    {
        // Guarantee covers the whole 100 deposit → no wallet hold at all.
        $this->seedClientGuarantee(100);

        $this->engine->moveBookingToInProgress($this->booking);

        $this->assertSame(
            Booking::STATUS_IN_PROGRESS,
            Booking::query()->whereKey($this->booking->id)->value('status')
        );

        // No wallet deposit was frozen.
        $frozen = Deposit::query()
            ->where('target_type', Booking::class)->where('target_id', $this->booking->id)
            ->where('status', 'frozen')->exists();
        $this->assertFalse($frozen, 'a fully-covered deposit needs no wallet freeze');

        $w = Wallet::query()->where('user_id', $this->clientId)->first();
        $this->assertEqualsWithDelta(1000.0, (float) $w->balance, 0.001, 'wallet untouched');
        $this->assertEqualsWithDelta(0.0, (float) $w->locked_balance, 0.001);

        // The whole deposit is frozen on the guarantee instead of the wallet.
        $g = UserGuarantee::query()->where('user_id', $this->clientId)->where('target_type', 'client')->first();
        $this->assertEqualsWithDelta(100.0, (float) $g->used_coverage_amount, 0.001, 'full deposit frozen on the guarantee');
    }
}
