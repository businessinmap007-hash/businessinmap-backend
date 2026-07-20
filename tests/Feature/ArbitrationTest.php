<?php

namespace Tests\Feature;

use App\Models\ArbitrationSession;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ArbitrationService;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\WalletService;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The arbitrator as an account level, and the record that keeps the level
 * accountable.
 *
 * An arbitrator is an admin holding a curated ability set, not a fourth
 * users.type — `type` says which app an account belongs to, and an arbitrator
 * comes through the same door as any other staff member. What is genuinely new
 * is the session ledger: whoever can move other people's money should leave a
 * record they cannot edit.
 */
class ArbitrationTest extends TestCase
{
    use DatabaseTransactions;

    private ArbitrationService $arbitration;
    private DisputeService $disputes;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arbitration = app(ArbitrationService::class);
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
        $admin->name = 'Arbitration Test Admin';
        $admin->email = 'arb-' . uniqid() . '@example.test';
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

    // ─────────────────────────── the role ───────────────────────────

    /** Appointing an arbitrator hands over exactly the abilities the job needs. */
    public function test_promoting_grants_the_arbitrator_abilities(): void
    {
        $admin = $this->makeAdmin();

        $this->arbitration->promote($admin);
        Bouncer::refresh();

        $this->assertTrue($this->arbitration->isArbitrator($admin->fresh()));

        foreach (ArbitrationService::ABILITIES as $ability) {
            $this->assertTrue($admin->fresh()->can($ability), "an arbitrator needs {$ability}");
        }
    }

    /**
     * MONEY is in the set on purpose: ruling on a dispute moves the escrow, so
     * an arbitrator who cannot move money cannot arbitrate.
     */
    public function test_the_ability_set_includes_money_and_stays_short(): void
    {
        $this->assertContains(AdminAbility::MONEY, ArbitrationService::ABILITIES);
        $this->assertContains(AdminAbility::DISPUTES, ArbitrationService::ABILITIES);

        // If this grows, someone is bundling unrelated power into the role.
        $this->assertLessThanOrEqual(3, count(ArbitrationService::ABILITIES));
    }

    /** A client or business account must never hold the panel role. */
    public function test_a_non_admin_cannot_be_appointed(): void
    {
        $client = User::query()->where('type', User::TYPE_CLIENT)->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->arbitration->promote($client);
    }

    /** A record that vanishes when someone is dismissed is not a record. */
    public function test_demoting_keeps_the_session_history(): void
    {
        $admin = $this->makeAdmin();
        $this->arbitration->promote($admin);

        $this->disputes->resolve($this->open(), 'refund_client', [], (int) $admin->id);

        $this->arbitration->demote($admin);
        Bouncer::refresh();

        $this->assertFalse($this->arbitration->isArbitrator($admin->fresh()));
        $this->assertFalse($admin->fresh()->can(AdminAbility::MONEY), 'the abilities go with the role');
        $this->assertSame(1, ArbitrationSession::query()->where('arbitrator_id', $admin->id)->count());
    }

    // ─────────────────────────── the record ───────────────────────────

    /** Written by the ruling, so nobody decides whether their own case counts. */
    public function test_every_ruling_writes_a_session(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $this->assertSame((int) $admin->id, (int) $session->arbitrator_id);
        $this->assertSame('refund_client', $session->outcome);
        $this->assertEqualsWithDelta(100.0, (float) $session->amount_to_client, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $session->amount_to_business, 0.01);
    }

    /** A split records the percentages AND what they came to in money. */
    public function test_a_split_session_records_both_shares(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'split', [
            'client_percent' => 70, 'business_percent' => 30,
        ], (int) $admin->id);

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->firstOrFail();

        $this->assertEqualsWithDelta(70.0, (float) $session->client_percent, 0.01);
        $this->assertEqualsWithDelta(70.0, (float) $session->amount_to_client, 0.01);
        $this->assertEqualsWithDelta(30.0, (float) $session->amount_to_business, 0.01);
    }

    /** A dispute is ruled once, so a second session row would be a bug. */
    public function test_a_dispute_gets_exactly_one_session(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'no_action', [], (int) $admin->id);
        $this->arbitration->recordSession($dispute->fresh(), 'refund_client', [], (int) $admin->id);

        $this->assertSame(1, ArbitrationSession::query()->where('dispute_id', $dispute->id)->count());
        $this->assertSame(
            'no_action',
            ArbitrationSession::query()->where('dispute_id', $dispute->id)->value('outcome'),
            'the first ruling is the one on record'
        );
    }

    // ─────────────────────────── the fine ───────────────────────────

    /** Cash out of the loser's wallet, on top of whatever the escrow did. */
    public function test_a_platform_fine_takes_balance_from_the_named_party(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $before = (float) $this->wallet((int) $this->booking->business_id)->balance;

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0, ArbitrationSession::FINE_NON_COMPLIANCE);

        $this->assertEqualsWithDelta(
            $before - 25.0,
            (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance,
            0.01
        );

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->firstOrFail();
        $this->assertEqualsWithDelta(25.0, (float) $session->platform_fine_amount, 0.01);
        $this->assertSame('business', $session->platform_fine_on);
    }

    /**
     * The fine is applied after the ruling, so the ruling notice knew nothing
     * about it — money leaves a wallet and needs its own notice.
     */
    public function test_a_fine_notifies_the_party_who_paid_it(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0, ArbitrationSession::FINE_NON_COMPLIANCE);

        $notice = \App\Models\AppNotification::query()
            ->where('notifiable_type', Dispute::class)
            ->where('notifiable_id', $dispute->id)
            ->where('title_ar', 'غرامة منصة على نزاع')
            ->get();

        $this->assertCount(1, $notice, 'only the party who paid is told');
        $this->assertSame((int) $this->booking->business_id, (int) $notice->first()->user_id);
        $this->assertStringContainsString('25.00', $notice->first()->body_ar);
    }

    /**
     * Losing is not a punishable act. A fine rests on misconduct or on refusing
     * the ruling — without one of those it is just a second penalty for being
     * wrong, on top of losing the escrow.
     */
    public function test_a_fine_needs_one_of_the_two_grounds(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->expectException(ValidationException::class);
        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0, 'lost_the_case');
    }

    /** A misconduct fine has to point at misconduct that was actually recorded. */
    public function test_a_conduct_fine_without_a_recorded_violation_is_refused(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->expectException(ValidationException::class);
        $this->arbitration->applyPlatformFine(
            $dispute->fresh(),
            'business',
            25.0,
            ArbitrationSession::FINE_CONDUCT
        );
    }

    public function test_a_conduct_fine_is_allowed_once_a_violation_is_on_the_record(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $thread = $this->disputes->room($dispute);
        app(\App\Services\ThreadService::class)->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $this->booking->business_id,
            recordedByUserId: (int) $admin->id,
            reason: 'إساءة لفظية'
        );

        $this->disputes->resolve($dispute->fresh(), 'refund_client', [], (int) $admin->id);

        $session = $this->arbitration->applyPlatformFine(
            $dispute->fresh(),
            'business',
            25.0,
            ArbitrationSession::FINE_CONDUCT
        );

        $this->assertSame(ArbitrationSession::FINE_CONDUCT, $session->platform_fine_reason);
        $this->assertEqualsWithDelta(25.0, (float) $session->platform_fine_amount, 0.01);
    }

    /** The ground is on the record, so the fine can be contested. */
    public function test_the_fine_records_its_ground(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $session = $this->arbitration->applyPlatformFine(
            $dispute->fresh(),
            'business',
            25.0,
            ArbitrationSession::FINE_NON_COMPLIANCE
        );

        $this->assertSame(ArbitrationSession::FINE_NON_COMPLIANCE, $session->platform_fine_reason);
    }

    /** One fine per ruling — a retry must not charge twice. */
    public function test_a_fine_cannot_be_applied_twice(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0, ArbitrationSession::FINE_NON_COMPLIANCE);
        $before = (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance;

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0, ArbitrationSession::FINE_NON_COMPLIANCE);

        $this->assertEqualsWithDelta(
            $before,
            (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance,
            0.01
        );
    }

    /** A fine belongs to a ruling, so there must be one first. */
    public function test_a_fine_before_a_ruling_is_refused(): void
    {
        $dispute = $this->open();

        $this->expectException(ValidationException::class);
        $this->arbitration->applyPlatformFine($dispute, 'business', 25.0, ArbitrationSession::FINE_NON_COMPLIANCE);
    }

    public function test_a_fine_needs_a_named_party(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->expectException(ValidationException::class);
        $this->arbitration->applyPlatformFine($dispute->fresh(), '', 25.0, ArbitrationSession::FINE_NON_COMPLIANCE);
    }

    // ─────────────────────── the arbitration fee ───────────────────────

    /** A fixed fee is exactly what was stated, whatever the case is worth. */
    public function test_accepting_a_session_fixes_a_flat_fee_up_front(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $session = $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);

        $this->assertEqualsWithDelta(40.0, (float) $session->fee_amount, 0.01);
        $this->assertNotNull($session->fee_terms_set_at);
        $this->assertNotNull($session->accepted_at);
        $this->assertNull($session->outcome, 'accepted is not decided');
        $this->assertTrue($session->isOpen());
    }

    /** A percentage is taken from what the case is actually worth. */
    public function test_a_percentage_fee_is_computed_from_the_disputed_amount(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        // The escrow is 100.
        $session = $this->arbitration->acceptSession($dispute, (int) $admin->id, 'percent', 10.0);

        $this->assertEqualsWithDelta(10.0, (float) $session->fee_amount, 0.01);
        $this->assertEqualsWithDelta(10.0, (float) $session->fee_value, 0.01, 'the terms are kept, not just the total');
    }

    /**
     * The price of a ruling must not be adjustable to the ruling, so the terms
     * are sealed the moment the case is accepted.
     */
    public function test_the_fee_cannot_be_rewritten_after_acceptance(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);

        $this->expectException(ValidationException::class);
        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 400.0);
    }

    public function test_an_impossible_percentage_is_refused(): void
    {
        $admin = $this->makeAdmin();

        $this->expectException(ValidationException::class);
        $this->arbitration->acceptSession($this->open(), (int) $admin->id, 'percent', 150.0);
    }

    /** The session accepted earlier is filled in, not duplicated. */
    public function test_ruling_fills_in_the_session_that_was_accepted(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $accepted = $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);

        $this->disputes->resolve($dispute->fresh(), 'refund_client', [], (int) $admin->id);

        $this->assertSame(1, ArbitrationSession::query()->where('dispute_id', $dispute->id)->count());

        $session = ArbitrationSession::findOrFail($accepted->id);
        $this->assertSame('refund_client', $session->outcome);
        $this->assertEqualsWithDelta(40.0, (float) $session->fee_amount, 0.01, 'the agreed fee survives the ruling');
    }

    /** The loser pays, and the loser is read off the ruling — not chosen. */
    public function test_the_fee_falls_on_the_losing_party(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);
        // The client won, so the business lost.
        $this->disputes->resolve($dispute->fresh(), 'refund_client', [], (int) $admin->id);

        $clientBefore = (float) $this->wallet((int) $this->booking->user_id)->balance;
        $businessBefore = (float) $this->wallet((int) $this->booking->business_id)->balance;

        $session = $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->assertSame('business', $session->fee_on);
        $this->assertEqualsWithDelta(
            $businessBefore - 40.0,
            (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance,
            0.01,
            'the loser pays the whole fee'
        );
        $this->assertEqualsWithDelta(
            $clientBefore,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01,
            'the winner pays nothing'
        );
    }

    /** The other direction, so the rule is not accidentally hard-coded. */
    public function test_the_client_pays_when_the_client_loses(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);

        $before = (float) $this->wallet((int) $this->booking->user_id)->balance;

        $this->assertSame('client', $this->arbitration->chargeArbitrationFee($dispute->fresh())->fee_on);
        $this->assertEqualsWithDelta(
            $before - 40.0,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01
        );
    }

    /** On a split, the smaller share is the losing side. */
    public function test_a_split_puts_the_fee_on_the_smaller_share(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);
        $this->disputes->resolve($dispute->fresh(), 'split', [
            'client_percent' => 70, 'business_percent' => 30,
        ], (int) $admin->id);

        $this->assertSame('business', $this->arbitration->chargeArbitrationFee($dispute->fresh())->fee_on);
    }

    /**
     * A ruling that names no loser cannot produce one. Refusing is the honest
     * answer — charging someone anyway would be arbitrary.
     */
    public function test_a_ruling_with_no_loser_cannot_be_charged(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);
        $this->disputes->resolve($dispute->fresh(), 'split', [
            'client_percent' => 50, 'business_percent' => 50,
        ], (int) $admin->id);

        $this->expectException(ValidationException::class);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());
    }

    /** Charging twice would double the price of one ruling. */
    public function test_the_fee_is_charged_only_once(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 40.0);
        $this->disputes->resolve($dispute->fresh(), 'refund_client', [], (int) $admin->id);

        $this->arbitration->chargeArbitrationFee($dispute->fresh());
        $after = (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance;

        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->assertEqualsWithDelta(
            $after,
            (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance,
            0.01
        );
    }

    /** Both parties learn the price before anything is heard. */
    public function test_accepting_announces_the_terms_to_both_parties(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'percent', 10.0);

        $notified = \App\Models\AppNotification::query()
            ->where('notifiable_type', Dispute::class)
            ->where('notifiable_id', $dispute->id)
            ->where('title_ar', 'قُبلت جلسة التحكيم')
            ->pluck('user_id')->map(fn ($v) => (int) $v)->sort()->values()->all();

        $this->assertSame(
            collect([(int) $this->booking->user_id, (int) $this->booking->business_id])->sort()->values()->all(),
            $notified
        );
    }

    // ─────────────────────── compensation ───────────────────────

    /**
     * The case the escrow cannot answer: shipping already paid on an order the
     * client refused. The loss is a real cost, not the deposit.
     */
    public function test_a_ruling_can_order_one_party_to_compensate_the_other(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'release_business', [], (int) $admin->id);

        $clientBefore = (float) $this->wallet((int) $this->booking->user_id)->balance;
        $businessBefore = (float) $this->wallet((int) $this->booking->business_id)->balance;

        $session = $this->arbitration->awardCompensation(
            $dispute->fresh(),
            'business',
            150.0,
            'رسوم شحن مدفوعة'
        );

        $this->assertEqualsWithDelta(150.0, (float) $session->compensation_amount, 0.01);
        $this->assertSame('business', $session->compensation_to);
        $this->assertNotNull($session->compensation_paid_at, 'the client could afford it');

        $this->assertEqualsWithDelta(
            $clientBefore - 150.0,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01,
            'the other party pays it'
        );
        $this->assertEqualsWithDelta(
            $businessBefore + 150.0,
            (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance,
            0.01
        );
    }

    /** It is a transfer between the parties — the platform takes nothing. */
    public function test_compensation_moves_between_the_parties_only(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'release_business', [], (int) $admin->id);

        $totalBefore = (float) $this->wallet((int) $this->booking->user_id)->balance
            + (float) $this->wallet((int) $this->booking->business_id)->balance;

        $this->arbitration->awardCompensation($dispute->fresh(), 'business', 150.0);

        $totalAfter = (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance
            + (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance;

        $this->assertEqualsWithDelta($totalBefore, $totalAfter, 0.001, 'nothing may leak to the platform');
    }

    /**
     * An empty wallet must not make the ruling vanish. The order stands unpaid —
     * and that unpaid state is exactly the non_compliance a fine rests on.
     */
    public function test_an_unaffordable_compensation_is_ordered_but_left_unpaid(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'release_business', [], (int) $admin->id);

        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);

        $session = $this->arbitration->awardCompensation($dispute->fresh(), 'business', 500.0);

        $this->assertEqualsWithDelta(500.0, (float) $session->compensation_amount, 0.01, 'the order stands');
        $this->assertNull($session->compensation_paid_at, 'but it is not paid');
        $this->assertEqualsWithDelta(
            10.0,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01,
            'no partial raid on the wallet'
        );
    }

    /** Paid the moment the payer tops up — which is why the order survives. */
    public function test_an_unpaid_compensation_settles_once_the_payer_can_afford_it(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'release_business', [], (int) $admin->id);

        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->awardCompensation($dispute->fresh(), 'business', 500.0);

        $this->wallet((int) $this->booking->user_id)->update(['balance' => 900]);

        $session = $this->arbitration->settleCompensation($dispute->fresh());

        $this->assertNotNull($session->compensation_paid_at);
        $this->assertEqualsWithDelta(
            400.0,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01
        );
    }

    public function test_compensation_cannot_be_ordered_before_a_ruling(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id, 'fixed', 10.0);

        $this->expectException(ValidationException::class);
        $this->arbitration->awardCompensation($dispute->fresh(), 'business', 150.0);
    }

    /** One order per ruling — a retry must not charge the payer twice. */
    public function test_compensation_is_ordered_only_once(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'release_business', [], (int) $admin->id);

        $this->arbitration->awardCompensation($dispute->fresh(), 'business', 150.0);
        $after = (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance;

        $this->arbitration->awardCompensation($dispute->fresh(), 'business', 150.0);

        $this->assertEqualsWithDelta(
            $after,
            (float) $this->wallet((int) $this->booking->user_id)->fresh()->balance,
            0.01
        );
    }

    // ─────────────────────────── the stats ───────────────────────────

    public function test_the_record_adds_up_across_cases(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);
        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 30.0, ArbitrationSession::FINE_NON_COMPLIANCE);

        $stats = $this->arbitration->statsFor((int) $admin->id);

        $this->assertSame(1, $stats['sessions']);
        $this->assertSame(0, $stats['open_sessions'], 'it was decided');
        $this->assertSame(['refund_client' => 1], $stats['by_outcome']);
        $this->assertEqualsWithDelta(30.0, $stats['fines_collected'], 0.01);
        $this->assertEqualsWithDelta(100.0, $stats['moved_to_clients'], 0.01);
        $this->assertNotNull($stats['last_session_at']);
    }

    // ─────────────────────────── the screen ───────────────────────────

    /** Appointing is a staffing decision: DISPUTES alone must not reach it. */
    public function test_the_register_is_gated_on_roles_not_disputes(): void
    {
        $admin = $this->makeAdmin();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::DISPUTES);
        Bouncer::refresh();

        $this->actingAs($admin)->get('/admin/arbitrators')->assertForbidden();

        Bouncer::allow($admin)->to(AdminAbility::ROLES);
        Bouncer::refresh();

        $this->actingAs($admin)->get('/admin/arbitrators')->assertOk();
    }

    public function test_the_register_and_the_record_screen_render(): void
    {
        $staffer = $this->makeAdmin();
        Bouncer::allow($staffer)->to(AdminAbility::ACCESS);
        Bouncer::allow($staffer)->to(AdminAbility::ROLES);
        Bouncer::refresh();

        $arbitrator = $this->makeAdmin();
        $this->arbitration->promote($arbitrator);
        $this->disputes->resolve($this->open(), 'release_business', [], (int) $arbitrator->id);

        $this->actingAs($staffer)->get('/admin/arbitrators')
            ->assertOk()
            ->assertSee($arbitrator->name);

        $this->actingAs($staffer)->get("/admin/arbitrators/{$arbitrator->id}")
            ->assertOk()
            ->assertSee('release_business');
    }
}
