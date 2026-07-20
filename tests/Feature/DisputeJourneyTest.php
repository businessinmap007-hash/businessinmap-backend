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

    /** Both sides posting a hold — the only shape in which a transfer is visible. */
    private function freezeBoth(float $each = 200.0): Deposit
    {
        $this->deposits->freezeForBooking($this->booking, $each * 2, [
            'wallet_hold_amount' => $each,
            'business_counter_hold_amount' => $each,
            'amount' => $each * 2,
        ]);

        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', $this->booking->id)
            ->latest('id')->firstOrFail();
    }

    /**
     * A ruling AWARDS the escrow: the loser's hold ends up with the winner.
     *
     * This is the test that was missing. The old ones asserted "nothing stays
     * locked" and "released_at is set" — both true while release() merely
     * unwound the escrow and handed every hold back to whoever posted it, so
     * release_business and refund_client moved identical money: none.
     */
    public function test_a_ruling_for_the_business_moves_the_clients_hold_across(): void
    {
        $this->freezeBoth(200);

        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        // Each posted 200, so each is down 200 from the 1000 they started with.
        $this->assertEqualsWithDelta(800.0, (float) $clientWallet->balance, 0.01, 'setup');
        $this->assertEqualsWithDelta(800.0, (float) $businessWallet->balance, 0.01, 'setup');

        $this->disputes->resolve($this->open(), 'release_business');

        $this->assertEqualsWithDelta(
            800.0,
            (float) $clientWallet->fresh()->balance,
            0.01,
            'the loser does not get their hold back'
        );
        $this->assertEqualsWithDelta(
            1200.0,
            (float) $businessWallet->fresh()->balance,
            0.01,
            'the winner takes the whole escrow'
        );
        $this->assertEqualsWithDelta(0.0, (float) $clientWallet->fresh()->locked_balance, 0.01);
    }

    /** The same, the other way, so the rule is not hard-coded to one side. */
    public function test_a_ruling_for_the_client_moves_the_businesss_hold_across(): void
    {
        $this->freezeBoth(200);

        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        $this->disputes->resolve($this->open(), 'refund_client');

        $this->assertEqualsWithDelta(1200.0, (float) $clientWallet->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(800.0, (float) $businessWallet->fresh()->balance, 0.01);
    }

    /** Whatever the ruling, the escrow is conserved between the two wallets. */
    public function test_a_ruling_neither_mints_nor_burns_escrow(): void
    {
        $this->freezeBoth(200);

        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        $this->disputes->resolve($this->open(), 'release_business');

        $this->assertEqualsWithDelta(
            2000.0,
            (float) $clientWallet->fresh()->balance + (float) $businessWallet->fresh()->balance,
            0.001
        );
    }

    /**
     * THE regression guard. A booking that simply completed must still UNWIND —
     * every hold back to whoever posted it. Making release() award the escrow
     * instead would have moved money on every successful booking on the
     * platform, which is exactly why the ruling got its own method.
     */
    public function test_a_normal_booking_release_still_unwinds_and_moves_nothing(): void
    {
        $this->freezeBoth(200);

        $clientWallet = $this->wallet((int) $this->booking->user_id);
        $businessWallet = $this->wallet((int) $this->booking->business_id);

        $this->deposits->releaseForBooking($this->booking);

        $this->assertEqualsWithDelta(
            1000.0,
            (float) $clientWallet->fresh()->balance,
            0.01,
            'a completed booking returns the client their own hold'
        );
        $this->assertEqualsWithDelta(
            1000.0,
            (float) $businessWallet->fresh()->balance,
            0.01,
            'and the business theirs — nobody pays anybody'
        );
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

    /** The command exists and is wired into the scheduler, not just defined. */
    public function test_the_dispute_command_is_scheduled(): void
    {
        $this->assertArrayHasKey('disputes:process', \Illuminate\Support\Facades\Artisan::all());

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->filter(fn ($command) => str_contains($command, 'disputes:process'));

        $this->assertTrue($scheduled->isNotEmpty(), 'defining the command is not enough — it must be scheduled');
    }

    /** Running the command chases the parties whose warning has come due. */
    public function test_the_command_sends_due_warnings(): void
    {
        $this->freeze();
        $dispute = $this->open();
        $dispute->update([
            'next_warning_at' => now()->subDay(),
            'against_user_id' => (int) $this->booking->business_id,
        ]);

        $this->artisan('disputes:process')->assertExitCode(0);

        $this->assertSame(1, (int) $dispute->fresh()->warning_count);
        $this->assertTrue($dispute->fresh()->next_warning_at->isFuture(), 'the next chase must be rescheduled');
    }

    /** An expired settlement window is handed to review, by time and not by a human. */
    public function test_an_expired_mutual_resolution_window_escalates_itself(): void
    {
        $this->freeze();
        $dispute = $this->open();
        $dispute->update([
            'mutual_resolution_deadline_at' => now()->subDays(30),
            'against_user_id' => (int) $this->booking->business_id,
        ]);

        $this->artisan('disputes:process')->assertExitCode(0);

        $this->assertSame(Dispute::STATUS_UNDER_REVIEW, $dispute->fresh()->status);
        $this->assertNotNull(data_get($dispute->fresh()->meta, 'escalated_at'));
    }

    /** Both parties are told, or escalation is just a silent status change. */
    public function test_escalation_notifies_both_parties(): void
    {
        $this->freeze();
        $dispute = $this->open();
        $dispute->update([
            'mutual_resolution_deadline_at' => now()->subDays(30),
            'against_user_id' => (int) $this->booking->business_id,
        ]);

        $this->disputes->escalateExpired();

        $notified = \App\Models\AppNotification::query()
            ->where('notifiable_type', Dispute::class)
            ->where('notifiable_id', $dispute->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->sort()->values()->all();

        $this->assertSame(
            collect([(int) $this->booking->user_id, (int) $this->booking->business_id])->sort()->values()->all(),
            $notified
        );
    }

    /** A window still open must not be escalated early. */
    public function test_a_live_window_is_left_alone(): void
    {
        $this->freeze();
        $dispute = $this->open();

        $this->assertTrue($dispute->mutual_resolution_deadline_at->isFuture(), 'setup: still live');

        $this->disputes->escalateExpired();

        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, $dispute->fresh()->status);
    }

    /** Escalating twice must not re-notify or re-stamp. */
    public function test_escalation_is_idempotent(): void
    {
        $this->freeze();
        $dispute = $this->open();
        $dispute->update(['mutual_resolution_deadline_at' => now()->subDays(30)]);

        $this->assertSame([$dispute->id], $this->disputes->escalateExpired());
        $this->assertSame([], $this->disputes->escalateExpired(), 'an already-escalated dispute is no longer due');
    }

    // ─────────────────────────── cooperation ───────────────────────────

    /** A party declares it; nothing infers it on their behalf. */
    public function test_a_party_can_record_that_they_are_cooperating(): void
    {
        $this->freeze();
        $dispute = $this->open();

        $this->disputes->recordCooperation($dispute, (int) $this->booking->user_id);

        $fresh = $dispute->fresh();
        $this->assertNotNull($fresh->client_cooperated_at);
        $this->assertNull($fresh->business_cooperated_at, 'one party declaring says nothing about the other');
    }

    /** Declaring twice must not move the timestamp — it is when you first showed up. */
    public function test_recording_cooperation_twice_keeps_the_first_time(): void
    {
        $this->freeze();
        $dispute = $this->open();

        $this->disputes->recordCooperation($dispute, (int) $this->booking->user_id);
        $first = $dispute->fresh()->client_cooperated_at;

        $this->disputes->recordCooperation($dispute->fresh(), (int) $this->booking->user_id);

        $this->assertEquals($first, $dispute->fresh()->client_cooperated_at);
    }

    public function test_a_stranger_cannot_record_cooperation(): void
    {
        $this->freeze();
        $dispute = $this->open();

        $stranger = User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->disputes->recordCooperation($dispute, (int) $stranger->id);
    }

    /**
     * Now that a party CAN declare cooperation, its absence means something and
     * is recorded on expiry — for whoever stayed silent, and only them.
     */
    public function test_escalation_flags_only_the_party_who_never_showed_up(): void
    {
        $this->freeze();
        $dispute = $this->open();

        $this->disputes->recordCooperation($dispute, (int) $this->booking->user_id);
        $dispute->fresh()->update(['mutual_resolution_deadline_at' => now()->subDays(30)]);

        $this->disputes->escalateExpired();

        $fresh = $dispute->fresh();
        $this->assertFalse((bool) $fresh->client_non_cooperation_flag, 'the client did show up');
        $this->assertTrue((bool) $fresh->business_non_cooperation_flag, 'the business never did');
    }

    /**
     * The flag is a mark an arbitrator reads, NOT a charge. A fee levied
     * automatically for missing a deadline would punish someone who was simply
     * not reading their phone, so nothing may compute money from this.
     */
    public function test_being_flagged_costs_nothing_by_itself(): void
    {
        $this->freeze(100);
        $dispute = $this->open();
        $dispute->update(['mutual_resolution_deadline_at' => now()->subDays(30)]);

        $businessWallet = $this->wallet((int) $this->booking->business_id);
        $before = (float) $businessWallet->balance;

        $this->disputes->escalateExpired();

        $this->assertTrue((bool) $dispute->fresh()->business_non_cooperation_flag);
        $this->assertEqualsWithDelta($before, (float) $businessWallet->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $dispute->fresh()->non_cooperation_fee_amount, 0.01);
    }

    // ───────────────── telling people what happened ─────────────────

    /**
     * The room announces the ruling, but a system message notifies nobody by
     * design — so without this a party who never opens the room has money
     * taken or returned and is told nothing.
     */
    public function test_a_ruling_notifies_both_parties(): void
    {
        $this->freeze(100);
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'refund_client');

        $notified = \App\Models\AppNotification::query()
            ->where('type', \App\Models\AppNotification::TYPE_DISPUTE)
            ->where('notifiable_type', Dispute::class)
            ->where('notifiable_id', $dispute->id)
            ->where('title_ar', 'صدر قرار في النزاع')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->sort()->values()->all();

        $this->assertSame(
            collect([(int) $this->booking->user_id, (int) $this->booking->business_id])->sort()->values()->all(),
            $notified
        );
    }

    /** Each side is told what THEY got, not a neutral summary to decode. */
    public function test_the_ruling_notice_states_the_amount_that_side_received(): void
    {
        $this->freeze(100);
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'refund_client');

        $clientBody = \App\Models\AppNotification::query()
            ->where('notifiable_id', $dispute->id)
            ->where('user_id', $this->booking->user_id)
            ->where('title_ar', 'صدر قرار في النزاع')
            ->value('body_ar');

        $businessBody = \App\Models\AppNotification::query()
            ->where('notifiable_id', $dispute->id)
            ->where('user_id', $this->booking->business_id)
            ->where('title_ar', 'صدر قرار في النزاع')
            ->value('body_ar');

        $this->assertStringContainsString('100.00', $clientBody, 'the client got the escrow back');
        $this->assertStringNotContainsString('100.00', $businessBody, 'the business got nothing');
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
