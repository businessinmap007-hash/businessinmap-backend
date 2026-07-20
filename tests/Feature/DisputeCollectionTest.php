<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\DisputeFee;
use App\Models\DisputeObligation;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ArbitrationService;
use App\Services\BookingDepositService;
use App\Services\DisputeCollectionService;
use App\Services\DisputeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Collecting what a ruling decided someone owes.
 *
 * The escalation the platform promises, in order: take it from the wallet, and
 * failing that leave it owed, block new business, and after a stated window
 * open the frozen guarantee without asking again. That last step is only
 * defensible because of the window, so these tests press hardest on the fact
 * that nothing is taken from a guarantee before the deadline.
 */
class DisputeCollectionTest extends TestCase
{
    use DatabaseTransactions;

    private ArbitrationService $arbitration;
    private DisputeService $disputes;
    private DisputeCollectionService $collections;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arbitration = app(ArbitrationService::class);
        $this->disputes = app(DisputeService::class);
        $this->collections = app(DisputeCollectionService::class);

        DisputeFee::query()->updateOrCreate(
            ['platform_service_id' => null],
            ['amount' => 100, 'is_active' => true]
        );

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        if (! $booking || ! $booking->user) {
            $this->markTestSkipped('Needs a booking with a client and a business.');
        }

        $this->booking = $booking;

        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        Dispute::query()->where('disputeable_type', Booking::class)->where('disputeable_id', $booking->id)->delete();

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $userId) {
            app(WalletService::class)->getOrCreateWallet($userId)->update([
                'status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0,
            ]);
        }

        app(BookingDepositService::class)->freezeForBooking($booking, 100.0, [
            'wallet_hold_amount' => 100.0,
            'business_counter_hold_amount' => 0.0,
            'amount' => 100.0,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = new User();
        $admin->name = 'Collection Test Admin';
        $admin->email = 'coll-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        return $admin;
    }

    private function open(): Dispute
    {
        return app(BookingDepositService::class)
            ->openDisputeForBooking($this->booking, (int) $this->booking->user_id);
    }

    private function wallet(int $userId): Wallet
    {
        return Wallet::query()->where('user_id', $userId)->firstOrFail();
    }

    /** Rule against the client and try to collect the session fee from them. */
    private function ruleAgainstClient(): Dispute
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);

        return $dispute->fresh();
    }

    // ─────────────────────── the money is there ───────────────────────

    public function test_an_affordable_fee_is_taken_straight_from_the_wallet(): void
    {
        $dispute = $this->ruleAgainstClient();

        $before = (float) $this->wallet((int) $this->booking->user_id)->balance;

        $this->arbitration->chargeArbitrationFee($dispute);

        $obligation = DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $this->assertSame(DisputeObligation::STATUS_PAID, $obligation->status);
        $this->assertSame(DisputeObligation::FROM_WALLET, $obligation->settled_from);
        $this->assertEqualsWithDelta(
            $before - 100.0,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01
        );
    }

    // ─────────────────────── the money is not there ───────────────────────

    /**
     * A ruling does not evaporate because a wallet is empty. Previously this
     * threw and nothing was recorded, so "paid" and "never collected" looked
     * identical afterwards.
     */
    public function test_an_unaffordable_fee_becomes_a_recorded_debt(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);

        $this->arbitration->chargeArbitrationFee($dispute);

        $obligation = DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $this->assertSame(DisputeObligation::STATUS_PENDING, $obligation->status);
        $this->assertEqualsWithDelta(100.0, (float) $obligation->amount, 0.01);
        $this->assertNotNull($obligation->due_at, 'a debt with no deadline can never be enforced');
        $this->assertEqualsWithDelta(
            10.0,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01,
            'no partial raid on the wallet'
        );
    }

    /** The window is what makes opening a guarantee later legitimate. */
    public function test_the_debt_is_given_the_stated_window(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);

        $this->arbitration->chargeArbitrationFee($dispute);

        $obligation = DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $this->assertEqualsWithDelta(
            DisputeCollectionService::GRACE_HOURS,
            now()->diffInHours($obligation->due_at, false),
            1
        );
    }

    /** Topping up settles it without anyone touching a guarantee. */
    public function test_a_top_up_within_the_window_settles_the_debt(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute);

        $this->wallet((int) $this->booking->user_id)->update(['balance' => 500]);

        $obligation = DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail();
        $settled = $this->collections->settle($obligation);

        $this->assertSame(DisputeObligation::STATUS_PAID, $settled->status);
        $this->assertSame(DisputeObligation::FROM_WALLET, $settled->settled_from);
    }

    /**
     * THE constraint. Before the deadline nothing may be taken from a
     * guarantee — taking it the moment a ruling lands is seizure, not
     * enforcement.
     */
    public function test_nothing_is_taken_from_the_guarantee_before_the_deadline(): void
    {
        $dispute = $this->ruleAgainstClient();

        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $clientWallet->update(['balance' => 10, 'locked_balance' => 5000]);

        $this->arbitration->chargeArbitrationFee($dispute);
        $this->collections->settleDue();

        $obligation = DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $this->assertSame(DisputeObligation::STATUS_PENDING, $obligation->status);
        $this->assertEqualsWithDelta(
            5000.0,
            (float) $clientWallet->fresh()->locked_balance,
            0.01,
            'the guarantee is untouched while the window is open'
        );
    }

    // ─────────────────────── the block on new business ───────────────────────

    /** Owing a ruling stops you starting new business with other people. */
    public function test_an_unpaid_debt_blocks_new_operations(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute);

        $this->assertTrue($this->collections->isBlocked((int) $this->booking->user_id));
        $this->assertEqualsWithDelta(100.0, $this->collections->outstandingFor((int) $this->booking->user_id), 0.01);

        Sanctum::actingAs($this->booking->user);

        $this->postJson('/api/v2/bookings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dispute_obligation');
    }

    /**
     * The block must not prevent paying. Topping up is the way out, and a block
     * that blocks the exit is a trap rather than a penalty.
     */
    public function test_the_block_lifts_the_moment_the_debt_is_settled(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute);

        $this->assertTrue($this->collections->isBlocked((int) $this->booking->user_id));

        $this->wallet((int) $this->booking->user_id)->update(['balance' => 500]);
        $this->collections->settle(
            DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail()
        );

        $this->assertFalse($this->collections->isBlocked((int) $this->booking->user_id));
    }

    /** The other party is not punished for their counterpart's debt. */
    public function test_the_block_only_touches_the_party_who_owes(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute);

        $this->assertFalse($this->collections->isBlocked((int) $this->booking->business_id));
    }

    // ─────────────────────── the scheduled sweep ───────────────────────

    /** Something has to notice the window closed, or it never closes. */
    public function test_the_scheduled_command_settles_due_obligations(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute);

        // The window closes, and the money arrives.
        DisputeObligation::query()->where('dispute_id', $dispute->id)->update(['due_at' => now()->subHour()]);
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 500]);

        $this->artisan('disputes:process')->assertExitCode(0);

        $this->assertSame(
            DisputeObligation::STATUS_PAID,
            DisputeObligation::query()->where('dispute_id', $dispute->id)->value('status')
        );
    }

    /** A retry must settle the existing debt, never create a second one. */
    public function test_charging_twice_does_not_double_the_debt(): void
    {
        $dispute = $this->ruleAgainstClient();
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);

        $this->arbitration->chargeArbitrationFee($dispute);
        $this->collections->charge($dispute, (int) $this->booking->user_id, DisputeObligation::TYPE_SESSION_FEE, 100.0);

        $this->assertSame(1, DisputeObligation::query()->where('dispute_id', $dispute->id)->count());
    }
}
