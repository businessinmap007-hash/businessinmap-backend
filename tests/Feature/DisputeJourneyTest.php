<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The dispute lifecycle, walked end to end on a real booking: freeze a deposit,
 * open the dispute, then rule on it and check the money actually moved.
 *
 * Written because `disputes` and `dispute_warnings` are both EMPTY in the
 * database — the whole mechanism is built but has never resolved a single real
 * case, so nothing had ever proved which rulings move money and which do not.
 *
 * Service level rather than API level on purpose: there is no dispute endpoint
 * in routes/api_v2.php at all. Opening one is admin-only
 * (AdminV2\DisputeController) or internal (BookingDepositService), which is
 * itself the finding — the party with the grievance has no way in.
 */
class DisputeJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private BookingDepositService $deposits;
    private DisputeService $disputes;
    private Booking $booking;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deposits = app(BookingDepositService::class);
        $this->disputes = app(DisputeService::class);

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
        $this->client = $booking->user;

        // Clean slate: no leftover deposit or dispute from another run.
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        Dispute::query()->where('disputeable_type', Booking::class)->where('disputeable_id', $booking->id)->delete();

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $userId) {
            app(WalletService::class)->getOrCreateWallet($userId)->update([
                'status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0,
            ]);
        }
    }

    /** Freeze a 100 wallet deposit against the booking. */
    private function freeze(float $amount = 100.0): Deposit
    {
        $this->deposits->freezeForBooking($this->booking, $amount, [
            'wallet_hold_amount' => $amount,
            'business_counter_hold_amount' => 0.0,
            'amount' => $amount,
        ]);

        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', $this->booking->id)
            ->latest('id')->firstOrFail();
    }

    private function wallet(int $userId): Wallet
    {
        return Wallet::query()->where('user_id', $userId)->firstOrFail();
    }

    private function open(): Dispute
    {
        return $this->deposits->openDisputeForBooking($this->booking, (int) $this->client->id);
    }

    // ─────────────────────────── opening ───────────────────────────

    /**
     * A new dispute starts in MUTUAL_RESOLUTION, not "open": the parties get a
     * window to settle it themselves before anyone rules.
     */
    public function test_opening_starts_a_mutual_resolution_window(): void
    {
        $this->freeze();

        $dispute = $this->open();

        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, $dispute->status);
        $this->assertNotNull($dispute->mutual_resolution_deadline_at, 'the window needs a deadline');
        $this->assertNotNull($dispute->next_warning_at, 'the parties must be chased before the deadline');
        $this->assertTrue($dispute->mutual_resolution_deadline_at->isAfter($dispute->opened_at));
        $this->assertSame((int) $this->client->id, (int) $dispute->opened_by_user_id);
    }

    /** Opening twice must not create a second live dispute over one booking. */
    public function test_opening_twice_returns_the_same_dispute(): void
    {
        $this->freeze();

        $first = $this->open();
        $second = $this->open();

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, Dispute::query()
            ->where('disputeable_type', Booking::class)
            ->where('disputeable_id', $this->booking->id)
            ->count());
    }

    /** Once the escrow is settled there is nothing left to argue about. */
    public function test_a_dispute_cannot_be_opened_after_the_deposit_is_final(): void
    {
        $deposit = $this->freeze();

        $this->disputes->resolve($this->open(), 'release_business');
        $this->assertTrue($deposit->fresh()->isFinal(), 'setup: the deposit should now be final');

        Dispute::query()->where('disputeable_id', $this->booking->id)->delete();

        $this->expectException(ValidationException::class);
        $this->open();
    }

    // ────────────────────────── the rulings ──────────────────────────

    /** refund_client: the held money goes back to the client. */
    public function test_ruling_for_the_client_returns_the_deposit(): void
    {
        $deposit = $this->freeze(100);
        $clientWallet = $this->wallet((int) $this->booking->user_id);

        $this->assertEqualsWithDelta(100.0, (float) $clientWallet->fresh()->locked_balance, 0.01, 'setup: held');

        $this->disputes->resolve($this->open(), 'refund_client');

        $after = $clientWallet->fresh();

        $this->assertEqualsWithDelta(0.0, (float) $after->locked_balance, 0.01, 'the hold must be lifted');
        $this->assertEqualsWithDelta(1000.0, (float) $after->balance, 0.01, 'the client gets their money back');
        $this->assertTrue($deposit->fresh()->isFinal());
    }

    /** release_business: the held money is settled out to the business. */
    public function test_ruling_for_the_business_settles_the_deposit(): void
    {
        $deposit = $this->freeze(100);
        $clientWallet = $this->wallet((int) $this->booking->user_id);

        $this->disputes->resolve($this->open(), 'release_business');

        $after = $clientWallet->fresh();

        $this->assertEqualsWithDelta(0.0, (float) $after->locked_balance, 0.01, 'the hold must be lifted either way');
        $this->assertTrue($deposit->fresh()->isFinal());
        $this->assertNotNull($deposit->fresh()->released_at, 'released, not refunded');
    }

    /** no_action: the ruling is recorded, the escrow is deliberately untouched. */
    public function test_no_action_records_the_ruling_and_leaves_the_escrow_alone(): void
    {
        $deposit = $this->freeze(100);

        $dispute = $this->disputes->resolve($this->open(), 'no_action');

        $this->assertSame(Dispute::STATUS_RESOLVED, $dispute->status);
        $this->assertFalse($deposit->fresh()->isFinal(), 'no_action must not settle the escrow');
    }

    /**
     * split: the escrow is actually divided, 60 to the client and 40 to the
     * business. The whole hold sits on the client's side, so the money reaches
     * the business by being unlocked to the client and then transferred across
     * — what matters is only where it ends up.
     */
    public function test_split_divides_the_escrow_between_both_parties(): void
    {
        $deposit = $this->freeze(100);
        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        $this->assertEqualsWithDelta(100.0, (float) $clientWallet->fresh()->locked_balance, 0.01, 'setup: held');

        $dispute = $this->disputes->resolve($this->open(), 'split', [
            'client_percent' => 60,
            'business_percent' => 40,
        ]);

        $this->assertSame(Dispute::STATUS_RESOLVED, $dispute->status);
        $this->assertSame('split', $dispute->resolution_type);

        $client = $clientWallet->fresh();
        $business = $businessWallet->fresh();

        $this->assertEqualsWithDelta(0.0, (float) $client->locked_balance, 0.01, 'nothing may stay frozen');
        $this->assertEqualsWithDelta(960.0, (float) $client->balance, 0.01, 'the client keeps 60 of the 100');
        $this->assertEqualsWithDelta(1040.0, (float) $business->balance, 0.01, 'the business receives 40');

        $this->assertTrue($deposit->fresh()->isSplit());
        $this->assertTrue($deposit->fresh()->isFinal(), 'a split is a settled deposit');
    }

    /** A split that awards everything to one side still balances to the total. */
    public function test_a_hundred_percent_split_behaves_like_a_full_award(): void
    {
        $this->freeze(100);
        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        $this->disputes->resolve($this->open(), 'split', [
            'client_percent' => 0,
            'business_percent' => 100,
        ]);

        $this->assertEqualsWithDelta(900.0, (float) $clientWallet->fresh()->balance, 0.01, 'the client keeps nothing');
        $this->assertEqualsWithDelta(1100.0, (float) $businessWallet->fresh()->balance, 0.01, 'the business takes all 100');
    }

    /** The escrow is conserved: an odd percentage must not mint or burn a cent. */
    public function test_a_split_with_an_awkward_percentage_conserves_the_total(): void
    {
        $this->freeze(100);
        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        $this->disputes->resolve($this->open(), 'split', [
            'client_percent' => 33.33,
            'business_percent' => 66.67,
        ]);

        $client = $clientWallet->fresh();
        $business = $businessWallet->fresh();

        $this->assertEqualsWithDelta(
            2000.0,
            (float) $client->balance + (float) $business->balance,
            0.001,
            'the two wallets together must hold exactly what they started with'
        );
        $this->assertEqualsWithDelta(933.33, (float) $client->balance, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $client->locked_balance, 0.01);
    }

    /** A settled deposit cannot be split afterwards. */
    public function test_a_released_deposit_cannot_then_be_split(): void
    {
        $deposit = $this->freeze(100);

        $this->disputes->resolve($this->open(), 'release_business');

        $this->expectException(ValidationException::class);
        app(\App\Services\DepositsEscrowService::class)->split($deposit->fresh(), 50, 50);
    }

    public function test_percentages_that_do_not_total_100_are_rejected(): void
    {
        $this->freeze();

        $this->expectException(ValidationException::class);
        $this->disputes->resolve($this->open(), 'split', ['client_percent' => 60, 'business_percent' => 30]);
    }

    public function test_an_unsupported_ruling_is_rejected(): void
    {
        $this->freeze();

        $this->expectException(ValidationException::class);
        $this->disputes->resolve($this->open(), 'give_it_all_to_me');
    }

    // ───────────────────────────── closing ─────────────────────────────

    public function test_a_resolved_dispute_cannot_be_ruled_on_again(): void
    {
        $this->freeze();

        $dispute = $this->disputes->resolve($this->open(), 'refund_client');

        $this->expectException(ValidationException::class);
        $this->disputes->resolve($dispute, 'release_business');
    }

    // ───────── where the journey stops (findings, pinned) ─────────

    /** The warning machinery itself works — when something calls it. */
    public function test_warnings_are_sent_when_the_service_is_invoked(): void
    {
        $this->freeze();
        $dispute = $this->open();

        // Bring the schedule due, the way time would.
        $dispute->update(['next_warning_at' => now()->subDay(), 'against_user_id' => (int) $this->booking->business_id]);

        app(\App\Services\DisputeWarningService::class)->sendDueWarnings();

        $this->assertGreaterThan(
            0,
            \App\Models\DisputeWarning::query()->where('dispute_id', $dispute->id)->count(),
            'both parties should be chased'
        );
        $this->assertSame(1, (int) $dispute->fresh()->warning_count);
    }

    /**
     * FINDING — nothing ever calls it.
     *
     * `next_warning_at` is computed on every dispute and read by no scheduled
     * job: Kernel::schedule() runs six commands and none of them is this one,
     * and no command references the service at all. So the parties are never
     * actually chased. Same shape as deletion_hold_reason before its screen.
     */
    public function test_no_scheduled_command_ever_sends_dispute_warnings(): void
    {
        $commands = array_keys(\Illuminate\Support\Facades\Artisan::all());

        $disputeCommands = array_values(array_filter(
            $commands,
            fn ($name) => str_contains($name, 'dispute')
        ));

        $this->assertSame(
            [],
            $disputeCommands,
            'A dispute command now exists — wire it into Kernel::schedule() and delete this test.'
        );
    }

    /**
     * FINDING — the mutual-resolution deadline is stored and never read.
     *
     * Nothing moves a dispute to `under_review` when the window expires; only an
     * admin clicking in AdminV2\DisputeController does. So an expired dispute
     * sits in mutual_resolution indefinitely, which is why an arbitration step
     * has nothing to trigger it.
     */
    public function test_an_expired_mutual_resolution_window_does_not_escalate_itself(): void
    {
        $this->freeze();
        $dispute = $this->open();

        $dispute->update(['mutual_resolution_deadline_at' => now()->subDays(30)]);

        $this->assertTrue($dispute->fresh()->mutual_resolution_deadline_at->isPast());
        $this->assertSame(
            Dispute::STATUS_MUTUAL_RESOLUTION,
            $dispute->fresh()->status,
            'nothing escalates an expired dispute — it waits for a human who is never told'
        );
    }

    /** The ruling is attributable — who decided, and when. */
    public function test_the_ruling_records_its_author(): void
    {
        $this->freeze();

        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin to attribute the ruling to.');
        }

        $dispute = $this->disputes->resolve($this->open(), 'refund_client', [], (int) $admin->id);

        $this->assertSame((int) $admin->id, (int) $dispute->resolved_by);
        $this->assertNotNull($dispute->resolved_at);
        $this->assertSame('refund_client', $dispute->resolution_type);
    }
}
