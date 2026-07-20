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

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0);

        $this->assertEqualsWithDelta(
            $before - 25.0,
            (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance,
            0.01
        );

        $session = ArbitrationSession::query()->where('dispute_id', $dispute->id)->firstOrFail();
        $this->assertEqualsWithDelta(25.0, (float) $session->platform_fine_amount, 0.01);
        $this->assertSame('business', $session->platform_fine_on);
    }

    /** One fine per ruling — a retry must not charge twice. */
    public function test_a_fine_cannot_be_applied_twice(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0);
        $before = (float) $this->wallet((int) $this->booking->business_id)->fresh()->balance;

        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 25.0);

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
        $this->arbitration->applyPlatformFine($dispute, 'business', 25.0);
    }

    public function test_a_fine_needs_a_named_party(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);

        $this->expectException(ValidationException::class);
        $this->arbitration->applyPlatformFine($dispute->fresh(), '', 25.0);
    }

    // ─────────────────────────── the stats ───────────────────────────

    public function test_the_record_adds_up_across_cases(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'refund_client', [], (int) $admin->id);
        $this->arbitration->applyPlatformFine($dispute->fresh(), 'business', 30.0);

        $stats = $this->arbitration->statsFor((int) $admin->id);

        $this->assertSame(1, $stats['sessions']);
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
