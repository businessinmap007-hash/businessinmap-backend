<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\DisputeFee;
use App\Models\DisputeObligation;
use App\Models\DisputeSettlement;
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
 * Closing a dispute as COMPLIED — the verdict that it is genuinely over.
 *
 * Resolving a dispute is not the same as finishing it: a fee may sit unpaid, a
 * settlement may be unconfirmed. "Complied" is a statement of fact the
 * arbitrator puts on the record, so these tests press on the one thing that
 * makes it trustworthy — that it is refused while anything is still outstanding.
 */
class DisputeComplianceTest extends TestCase
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
        $admin->name = 'Compliance Test Admin';
        $admin->email = 'comp-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::DISPUTES);
        Bouncer::refresh();

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

    /** A clean win: the loser could pay the fee, so nothing is left owing. */
    public function test_a_fully_paid_ruling_can_be_closed_as_complied(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->assertTrue($this->disputes->complianceState($dispute->fresh())['compliant']);

        $closed = $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id);

        $this->assertSame(Dispute::STATUS_CLOSED, $closed->status);
        $this->assertSame('complied', $closed->closed_reason);
        $this->assertNotNull($closed->closed_at);
    }

    /** THE guard: an unpaid fee means the ruling was not carried out. */
    public function test_compliance_is_refused_while_a_fee_is_unpaid(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);

        // The loser cannot pay, so the fee becomes a standing debt.
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->assertFalse($this->disputes->complianceState($dispute->fresh())['compliant']);

        $this->expectException(ValidationException::class);
        $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id);
    }

    /** Paying the debt makes the case compliant, and then it can close. */
    public function test_settling_the_debt_unlocks_the_compliance_close(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);

        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        // They top up; the sweep collects.
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 500]);
        app(\App\Services\DisputeCollectionService::class)->settle(
            DisputeObligation::query()->where('dispute_id', $dispute->id)->firstOrFail()
        );

        $this->assertTrue($this->disputes->complianceState($dispute->fresh())['compliant']);
        $this->assertSame(
            'complied',
            $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id)->closed_reason
        );
    }

    /** An off-app settlement proposed but not yet confirmed is money in the air. */
    public function test_compliance_is_refused_while_a_settlement_is_unconfirmed(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'no_action', [], (int) $admin->id);

        DisputeSettlement::query()->create([
            'dispute_id' => $dispute->id,
            'proposed_by_user_id' => (int) $this->booking->business_id,
            'proposed_by_role' => 'business',
            'payer_side' => 'business',
            'amount' => 200,
            'status' => DisputeSettlement::STATUS_PROPOSED,
        ]);

        $state = $this->disputes->complianceState($dispute->fresh());
        $this->assertFalse($state['compliant']);
        $this->assertTrue($state['pending_settlement']);

        $this->expectException(ValidationException::class);
        $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id);
    }

    /** Compliance is a verdict on a ruling, so there must be a ruling first. */
    public function test_compliance_cannot_be_certified_before_a_ruling(): void
    {
        $dispute = $this->open();

        $this->expectException(ValidationException::class);
        $this->disputes->closeWithCompliance($dispute, null);
    }

    /** Both parties are told the matter is over. */
    public function test_a_compliance_close_notifies_both_parties(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id);

        $notified = \App\Models\AppNotification::query()
            ->where('notifiable_type', Dispute::class)
            ->where('notifiable_id', $dispute->id)
            ->where('title_ar', 'أُغلق النزاع')
            ->pluck('user_id')->map(fn ($v) => (int) $v)->sort()->values()->all();

        $this->assertSame(
            collect([(int) $this->booking->user_id, (int) $this->booking->business_id])->sort()->values()->all(),
            $notified
        );
    }

    // ─────────────────────────── the admin screen ───────────────────────────

    public function test_the_compliance_close_works_from_the_panel(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->actingAs($admin)
            ->post("/admin/disputes/{$dispute->id}/close-complied")
            ->assertRedirect();

        $this->assertSame('complied', Dispute::findOrFail($dispute->id)->closed_reason);
    }

    public function test_the_panel_refuses_a_compliance_close_with_a_debt_outstanding(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->actingAs($admin)
            ->post("/admin/disputes/{$dispute->id}/close-complied")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(Dispute::STATUS_RESOLVED, Dispute::findOrFail($dispute->id)->status);
    }

    /** A plain admin close is honestly labelled, never dressed up as compliance. */
    public function test_a_plain_close_is_recorded_as_admin_closed(): void
    {
        $admin = $this->makeAdmin();
        $dispute = $this->open();

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->wallet((int) $this->booking->user_id)->update(['balance' => 10]);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        $this->actingAs($admin)->post("/admin/disputes/{$dispute->id}/close")->assertRedirect();

        $this->assertSame('admin_closed', Dispute::findOrFail($dispute->id)->closed_reason);
    }
}
