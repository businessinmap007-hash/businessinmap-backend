<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\User;
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

        // Clean deposit slate + no fees (isolate the deposit hold).
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        foreach ([$this->clientId, $businessId] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Ready + confirmed booking priced at 1000.
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
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
}
