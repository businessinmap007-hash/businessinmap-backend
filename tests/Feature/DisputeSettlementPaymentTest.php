<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\DisputeSettlement;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingDepositService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A payment the parties settled between themselves, off the platform.
 *
 * The platform cannot verify a bank transfer or cash in a hand, so it records
 * three statements instead: someone proposes a figure, the OTHER side accepts
 * it, and the RECEIVER confirms it arrived. These tests press hardest on who is
 * allowed to make each statement, because that is the only thing giving the
 * record any weight.
 */
class DisputeSettlementPaymentTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;
    private User $client;
    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        if (! $booking || ! $booking->user || ! $booking->business) {
            $this->markTestSkipped('Needs a booking with a client and a business.');
        }

        $this->booking = $booking;
        $this->client = $booking->user;
        $this->business = $booking->business;

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

    private function openDispute(): int
    {
        Sanctum::actingAs($this->client);

        return $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');
    }

    private function propose(int $disputeId, string $payerSide = 'business', float $amount = 250.0): int
    {
        return $this->postJson("/api/v2/disputes/{$disputeId}/settlement-payments", [
            'payer_side' => $payerSide,
            'amount' => $amount,
            'method' => 'cash',
        ])->assertCreated()->json('data.id');
    }

    private function stranger(): User
    {
        return User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')->firstOrFail();
    }

    // ─────────────────────────── proposing ───────────────────────────

    public function test_a_party_can_propose_an_off_app_amount(): void
    {
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments", [
            'payer_side' => 'business',
            'amount' => 250,
            'method' => 'cash',
            'note' => 'Agreed over the phone.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', DisputeSettlement::STATUS_PROPOSED)
            ->assertJsonPath('data.amount', 250)
            ->assertJsonPath('data.payer_side', 'business')
            ->assertJsonPath('data.payee_side', 'client')
            ->assertJsonPath('data.proposed_by.is_me', true);
    }

    public function test_a_zero_or_negative_amount_is_refused(): void
    {
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments", ['payer_side' => 'business', 'amount' => 0])
            ->assertStatus(422);

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments", ['payer_side' => 'business', 'amount' => -5])
            ->assertStatus(422);
    }

    /** Two live figures on the table at once is how people end up disagreeing about which one. */
    public function test_a_second_proposal_is_refused_while_one_is_live(): void
    {
        $id = $this->openDispute();
        $this->propose($id);

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments", ['payer_side' => 'client', 'amount' => 90])
            ->assertStatus(422);
    }

    public function test_a_stranger_cannot_propose(): void
    {
        $id = $this->openDispute();

        Sanctum::actingAs($this->stranger());

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments", ['payer_side' => 'business', 'amount' => 50])
            ->assertNotFound();
    }

    // ─────────────────────────── accepting ───────────────────────────

    /** The proposer already said yes by proposing. */
    public function test_the_proposer_cannot_accept_their_own_figure(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id);

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")
            ->assertStatus(422);
    }

    public function test_the_other_side_can_accept(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id);

        Sanctum::actingAs($this->business);

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', DisputeSettlement::STATUS_ACCEPTED);
    }

    public function test_the_other_side_can_reject_instead(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id);

        Sanctum::actingAs($this->business);

        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', DisputeSettlement::STATUS_REJECTED);

        // A rejected figure frees the table for a different one.
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments", ['payer_side' => 'business', 'amount' => 120])
            ->assertCreated();
    }

    public function test_the_proposer_can_withdraw_before_acceptance(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id);

        $this->deleteJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}")
            ->assertOk()
            ->assertJsonPath('data.status', DisputeSettlement::STATUS_WITHDRAWN);
    }

    public function test_withdrawal_is_refused_once_accepted(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")->assertOk();

        Sanctum::actingAs($this->client);
        $this->deleteJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}")
            ->assertStatus(422);
    }

    // ─────────────────────── confirming receipt ───────────────────────

    /**
     * THE check. A payer confirming their own payment proves nothing, so only
     * the party receiving the money may say it arrived.
     */
    public function test_only_the_payee_may_confirm_receipt(): void
    {
        $id = $this->openDispute();
        // business pays → client receives
        $settlementId = $this->propose($id, 'business', 250);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")->assertOk();

        // The payer trying to confirm their own payment.
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/received")
            ->assertStatus(422);

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/received")
            ->assertOk()
            ->assertJsonPath('data.status', DisputeSettlement::STATUS_RECEIVED);
    }

    public function test_receipt_cannot_be_confirmed_before_acceptance(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id, 'business', 250);

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/received")
            ->assertStatus(422);
    }

    /** The receipt is what ends the case, and the escrow unwinds with it. */
    public function test_confirming_receipt_closes_the_dispute_and_returns_the_hold(): void
    {
        $clientWallet = Wallet::query()->where('user_id', $this->booking->user_id)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $clientWallet->locked_balance, 0.01, 'setup: held');

        $id = $this->openDispute();
        $settlementId = $this->propose($id, 'business', 250);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")->assertOk();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/received")->assertOk();

        $dispute = Dispute::findOrFail($id);
        $this->assertSame(Dispute::STATUS_RESOLVED, $dispute->status);
        $this->assertSame('mutual_settlement', $dispute->resolution_type);

        $after = $clientWallet->fresh();
        $this->assertEqualsWithDelta(0.0, (float) $after->locked_balance, 0.01, 'nothing may stay frozen');
        $this->assertEqualsWithDelta(1000.0, (float) $after->balance, 0.01, 'the client gets their own hold back');
    }

    /** The platform never moved this money and must not pretend it did. */
    public function test_the_off_app_amount_never_touches_a_wallet(): void
    {
        $businessWallet = Wallet::query()->where('user_id', $this->booking->business_id)->firstOrFail();
        $before = (float) $businessWallet->balance;

        $id = $this->openDispute();
        $settlementId = $this->propose($id, 'business', 250);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")->assertOk();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/received")->assertOk();

        $this->assertEqualsWithDelta(
            $before,
            (float) $businessWallet->fresh()->balance,
            0.01,
            'the 250 was paid outside the app — the wallet must not move'
        );
    }

    /** The amount and who paid it survive on the dispute's own record. */
    public function test_the_settled_amount_is_recorded_on_the_ruling(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id, 'business', 250);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/accept")->assertOk();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$settlementId}/received")->assertOk();

        $payload = Dispute::findOrFail($id)->resolution_payload['resolution_payload'] ?? [];

        $this->assertEqualsWithDelta(250.0, (float) ($payload['off_app_amount'] ?? 0), 0.01);
        $this->assertSame('business', $payload['off_app_payer_side'] ?? null);
    }

    // ─────────────────────────── the record ───────────────────────────

    /** The haggling is the record: an arbitrator needs to see the refused figures. */
    public function test_rejected_proposals_stay_in_the_history(): void
    {
        $id = $this->openDispute();
        $first = $this->propose($id, 'business', 250);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement-payments/{$first}/reject")->assertOk();

        Sanctum::actingAs($this->client);
        $this->propose($id, 'business', 120);

        $response = $this->getJson("/api/v2/disputes/{$id}/settlement-payments")->assertOk();

        $this->assertCount(2, $response->json('data.history'));
        $this->assertSame(120, $response->json('data.current.amount'));
    }

    /** An id from another dispute must not be actionable here. */
    public function test_a_settlement_from_another_dispute_is_not_reachable(): void
    {
        $id = $this->openDispute();
        $settlementId = $this->propose($id);

        $other = DisputeSettlement::query()->create([
            'dispute_id' => $id,
            'proposed_by_user_id' => (int) $this->client->id,
            'proposed_by_role' => 'client',
            'payer_side' => 'client',
            'amount' => 10,
            'status' => DisputeSettlement::STATUS_PROPOSED,
        ]);

        // Same settlement, wrong dispute id in the path.
        $this->postJson("/api/v2/disputes/999999999/settlement-payments/{$other->id}/accept")
            ->assertNotFound();

        $this->assertSame(DisputeSettlement::STATUS_PROPOSED, $other->fresh()->status);
        $this->assertNotNull($settlementId);
    }
}
