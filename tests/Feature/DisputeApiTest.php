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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The door into the dispute mechanism, from the app's side.
 *
 * Everything else about disputes was reachable only by an admin or by internal
 * service calls, which is why `disputes` had zero rows: the party with the
 * grievance had no endpoint at all. These tests walk the client's actual path —
 * open, list, read — and press on the authorization, which is the part that
 * would otherwise repeat v1's hole of letting anyone touch anyone's escrow.
 */
class DisputeApiTest extends TestCase
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

    /** A stranger is a stranger even when signed in. */
    private function someoneElse(): User
    {
        return User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')
            ->firstOrFail();
    }

    // ─────────────────────────── opening ───────────────────────────

    public function test_the_client_can_open_a_dispute_on_their_own_booking(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", [
            'reason_code' => 'not_as_described',
            'reason_text' => 'The room was not the one advertised.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', Dispute::STATUS_MUTUAL_RESOLUTION)
            ->assertJsonPath('data.reason_code', 'not_as_described')
            ->assertJsonPath('data.my_role', 'opener');

        $this->assertNotNull($response->json('data.mutual_resolution_deadline_at'));
        $this->assertDatabaseHas('disputes', [
            'disputeable_id' => $this->booking->id,
            'opened_by_user_id' => $this->booking->user_id,
            'against_user_id' => $this->booking->business_id,
        ]);
    }

    /** The business is a party too — a grievance can run either way. */
    public function test_the_business_can_open_a_dispute_on_its_own_booking(): void
    {
        Sanctum::actingAs($this->business);

        $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'no_show'])
            ->assertOk()
            ->assertJsonPath('data.my_role', 'opener');

        $this->assertDatabaseHas('disputes', [
            'disputeable_id' => $this->booking->id,
            'opened_by_user_id' => $this->booking->business_id,
            'against_user_id' => $this->booking->user_id,
        ]);
    }

    /**
     * THE check. Without it any signed-in account could freeze a stranger's
     * escrow — the v1 /deposits hole, rebuilt in a new namespace.
     */
    public function test_a_stranger_cannot_open_a_dispute_on_someone_elses_booking(): void
    {
        Sanctum::actingAs($this->someoneElse());

        $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->assertNotFound();

        $this->assertDatabaseMissing('disputes', ['disputeable_id' => $this->booking->id]);
    }

    public function test_opening_requires_authentication(): void
    {
        $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->assertUnauthorized();
    }

    public function test_an_unknown_reason_code_is_rejected(): void
    {
        Sanctum::actingAs($this->client);

        $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'because_i_said_so'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason_code');
    }

    public function test_opening_twice_returns_the_same_dispute(): void
    {
        Sanctum::actingAs($this->client);

        $first = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late']);
        $second = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality']);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Dispute::query()->where('disputeable_id', $this->booking->id)->count());
    }

    /** Nothing left to argue about once the escrow is settled. */
    public function test_a_dispute_cannot_be_opened_after_the_escrow_is_settled(): void
    {
        app(DisputeService::class); // resolve the container before acting
        $deposit = Deposit::query()
            ->where('target_type', Booking::class)->where('target_id', $this->booking->id)
            ->latest('id')->firstOrFail();

        app(\App\Services\DepositsEscrowService::class)->release($deposit);

        Sanctum::actingAs($this->client);

        $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->assertStatus(422);
    }

    // ─────────────────────────── reading ───────────────────────────

    public function test_both_parties_see_the_dispute_with_the_right_role(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        $this->getJson("/api/v2/disputes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.my_role', 'opener');

        Sanctum::actingAs($this->business);

        $this->getJson("/api/v2/disputes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.my_role', 'respondent');
    }

    public function test_a_stranger_cannot_read_a_dispute(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        Sanctum::actingAs($this->someoneElse());

        // 404, not 403: a stranger must not learn that the dispute exists.
        $this->getJson("/api/v2/disputes/{$id}")->assertNotFound();
    }

    public function test_the_list_only_shows_disputes_i_am_a_party_to(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        $this->getJson('/api/v2/disputes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        Sanctum::actingAs($this->someoneElse());

        $this->assertNotContains(
            $id,
            collect($this->getJson('/api/v2/disputes')->assertOk()->json('data'))->pluck('id')->all()
        );
    }

    /** The role filter must narrow the caller's own list, never widen it. */
    public function test_the_role_filter_cannot_widen_the_list(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        // The client opened it, so asking for "respondent" must return nothing.
        $this->assertNotContains(
            $id,
            collect($this->getJson('/api/v2/disputes?role=respondent')->assertOk()->json('data'))->pluck('id')->all()
        );

        $this->assertContains(
            $id,
            collect($this->getJson('/api/v2/disputes?role=opener')->assertOk()->json('data'))->pluck('id')->all()
        );
    }

    /** The file stays private: admin notes and the raw payload are not a party's business. */
    public function test_the_payload_and_internal_meta_are_never_exposed(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        $data = $this->getJson("/api/v2/disputes/{$id}")->assertOk()->json('data');

        $this->assertArrayNotHasKey('meta', $data);
        $this->assertArrayNotHasKey('resolution_payload', $data);
    }

    /** A party may see how the escrow was divided — that is the ruling itself. */
    public function test_a_split_ruling_shows_its_percentages(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        app(DisputeService::class)->resolve(
            Dispute::findOrFail($id),
            'split',
            ['client_percent' => 70, 'business_percent' => 30, 'notes' => 'internal admin note']
        );

        $data = $this->getJson("/api/v2/disputes/{$id}")->assertOk()->json('data');

        $this->assertSame('split', $data['resolution_type']);
        $this->assertEqualsWithDelta(70.0, $data['resolution']['client_percent'], 0.01);
        $this->assertEqualsWithDelta(30.0, $data['resolution']['business_percent'], 0.01);
        $this->assertStringNotContainsString('internal admin note', json_encode($data));
    }

    // ─────────────────────────── cooperation ───────────────────────────

    public function test_a_party_can_declare_cooperation_through_the_api(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        $this->postJson("/api/v2/disputes/{$id}/cooperate")
            ->assertOk()
            ->assertJsonPath('my_side', 'client');

        $this->assertNotNull(
            $this->getJson("/api/v2/disputes/{$id}")->json('data.cooperation.client_at')
        );
        $this->assertNull(
            $this->getJson("/api/v2/disputes/{$id}")->json('data.cooperation.business_at')
        );
    }

    /** The business is the business even when it opened the case. */
    public function test_the_side_is_read_from_the_booking_not_from_who_opened_it(): void
    {
        Sanctum::actingAs($this->business);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'no_show'])
            ->json('data.id');

        $this->postJson("/api/v2/disputes/{$id}/cooperate")
            ->assertOk()
            ->assertJsonPath('my_side', 'business');

        $this->assertNotNull($this->getJson("/api/v2/disputes/{$id}")->json('data.cooperation.business_at'));
    }

    public function test_a_stranger_cannot_declare_cooperation(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'late'])
            ->json('data.id');

        Sanctum::actingAs($this->someoneElse());

        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertNotFound();
    }

    // ────────────────────── asking for a judge ──────────────────────

    private function openDispute(): int
    {
        return $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');
    }

    /** One party is enough — needing both would let a stonewaller block it. */
    public function test_a_party_can_ask_for_arbitration_without_waiting_for_the_deadline(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();

        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")
            ->assertOk()
            ->assertJsonPath('data.status', Dispute::STATUS_UNDER_REVIEW);

        $dispute = Dispute::findOrFail($id);
        $this->assertSame('party_request', data_get($dispute->meta, 'escalated_by'));
        $this->assertSame((int) $this->client->id, (int) data_get($dispute->meta, 'escalation_requested_by'));
    }

    // ───────── paying for what you ask for ─────────

    private function setSessionFee(int $amount): void
    {
        \App\Models\DisputeFee::query()->updateOrCreate(
            ['platform_service_id' => null],
            ['amount' => $amount, 'is_active' => true]
        );
    }

    private function setBalance(int $userId, float $balance): void
    {
        \App\Models\Wallet::query()->where('user_id', $userId)->update(['balance' => $balance]);
    }

    /**
     * You may only ask for a paid service you could pay for. The fee falls on
     * whoever loses, and the asker is the one choosing to create that cost.
     */
    public function test_asking_for_arbitration_needs_the_session_fee_in_the_wallet(): void
    {
        $this->setSessionFee(200);
        $this->setBalance((int) $this->booking->user_id, 50);

        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();

        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")
            ->assertStatus(422)
            ->assertJsonValidationErrors('balance');

        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, Dispute::findOrFail($id)->status);

        $this->setBalance((int) $this->booking->user_id, 500);

        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")
            ->assertOk()
            ->assertJsonPath('data.status', Dispute::STATUS_UNDER_REVIEW);
    }

    /**
     * Deliberately NOT gated on the other side's balance: refusing because the
     * RESPONDENT is broke would let anyone dodge arbitration by emptying their
     * wallet — the stonewalling this path exists to defeat.
     */
    public function test_a_broke_respondent_cannot_block_the_request(): void
    {
        $this->setSessionFee(200);
        $this->setBalance((int) $this->booking->user_id, 500);
        $this->setBalance((int) $this->booking->business_id, 0);

        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();

        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")
            ->assertOk()
            ->assertJsonPath('data.status', Dispute::STATUS_UNDER_REVIEW);
    }

    /** The price and the shortfall are readable before the button is tapped. */
    public function test_the_price_is_shown_before_asking(): void
    {
        $this->setSessionFee(200);
        $this->setBalance((int) $this->booking->user_id, 50);

        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->getJson("/api/v2/disputes/{$id}")
            ->assertOk()
            ->assertJsonPath('arbitration.fee', 200)
            ->assertJsonPath('arbitration.balance', 50)
            ->assertJsonPath('arbitration.sufficient', false);
    }

    /**
     * Time is not a purchase. An expired window escalates whatever anyone's
     * balance is — nobody chose that cost, so nobody can be priced out of it.
     */
    public function test_the_deadline_escalates_a_penniless_dispute_anyway(): void
    {
        $this->setSessionFee(200);
        $this->setBalance((int) $this->booking->user_id, 0);
        $this->setBalance((int) $this->booking->business_id, 0);

        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        Dispute::query()->whereKey($id)->update(['mutual_resolution_deadline_at' => now()->subDay()]);

        app(\App\Services\DisputeService::class)->escalateExpired();

        $this->assertSame(Dispute::STATUS_UNDER_REVIEW, Dispute::findOrFail($id)->status);
    }

    /** You have to show up before you can demand a judge. */
    public function test_asking_before_declaring_cooperation_is_refused(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")
            ->assertStatus(422)
            ->assertJsonValidationErrors('cooperation');

        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, Dispute::findOrFail($id)->status);
    }

    /**
     * The window is still open on a party's request, so the other side must not
     * be marked uncooperative for a deadline that has not passed.
     */
    public function test_asking_does_not_flag_the_other_party(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")->assertOk();

        $dispute = Dispute::findOrFail($id);
        $this->assertFalse((bool) $dispute->client_non_cooperation_flag);
        $this->assertFalse((bool) $dispute->business_non_cooperation_flag, 'the window had not expired');
    }

    public function test_asking_twice_is_harmless(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")
            ->assertOk()
            ->assertJsonPath('data.status', Dispute::STATUS_UNDER_REVIEW);
    }

    public function test_a_stranger_cannot_ask_for_arbitration(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();

        Sanctum::actingAs($this->someoneElse());

        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")->assertNotFound();
    }

    /** Both sides are told, and the room records why the case moved. */
    public function test_asking_announces_itself_to_both_parties(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")->assertOk();

        $notified = \App\Models\AppNotification::query()
            ->where('notifiable_type', Dispute::class)
            ->where('notifiable_id', $id)
            ->where('title_ar', 'طُلب التحكيم في النزاع')
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->sort()->values()->all();

        $this->assertSame(
            collect([(int) $this->booking->user_id, (int) $this->booking->business_id])->sort()->values()->all(),
            $notified
        );
    }

    // ──────────────────── settling it between themselves ────────────────────

    /** One tap is a proposal, not a settlement. */
    public function test_one_party_agreeing_does_not_close_the_dispute(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.settlement.complete', false);

        $dispute = Dispute::findOrFail($id);
        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, $dispute->status);
        $this->assertNotNull($dispute->client_settlement_agreed_at);
        $this->assertNull($dispute->business_settlement_agreed_at);
    }

    /** The second tap ends it, and the escrow unwinds to whoever posted it. */
    public function test_both_parties_agreeing_closes_the_dispute_and_returns_the_hold(): void
    {
        $clientWallet = \App\Models\Wallet::query()->where('user_id', $this->booking->user_id)->firstOrFail();

        $this->assertEqualsWithDelta(100.0, (float) $clientWallet->locked_balance, 0.01, 'setup: held');

        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.settlement.complete', true)
            ->assertJsonPath('data.status', Dispute::STATUS_RESOLVED)
            ->assertJsonPath('data.resolution_type', 'mutual_settlement');

        $after = $clientWallet->fresh();
        $this->assertEqualsWithDelta(0.0, (float) $after->locked_balance, 0.01, 'nothing may stay frozen');
        $this->assertEqualsWithDelta(1000.0, (float) $after->balance, 0.01, 'the client gets their own hold back');
    }

    /** A mis-tap must not become a settlement the moment the other side agrees. */
    public function test_an_agreement_can_be_withdrawn_before_the_other_side_confirms(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();
        $this->deleteJson("/api/v2/disputes/{$id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.settlement.client_agreed_at', null);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.settlement.complete', false);

        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, Dispute::findOrFail($id)->status);
    }

    public function test_a_settlement_cannot_be_withdrawn_after_it_completed(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        $this->deleteJson("/api/v2/disputes/{$id}/settlement")->assertStatus(422);
    }

    /** An agreement the parties reached themselves beats a pending arbitration. */
    public function test_the_parties_can_still_settle_after_arbitration_was_requested(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/cooperate")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/request-arbitration")->assertOk();

        $this->assertSame(Dispute::STATUS_UNDER_REVIEW, Dispute::findOrFail($id)->status);

        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.status', Dispute::STATUS_RESOLVED);
    }

    /** Pressing twice is one agreement, not two. */
    public function test_agreeing_twice_is_harmless(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();
        $first = Dispute::findOrFail($id)->client_settlement_agreed_at;

        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        $this->assertEquals($first, Dispute::findOrFail($id)->client_settlement_agreed_at);
        $this->assertSame(Dispute::STATUS_MUTUAL_RESOLUTION, Dispute::findOrFail($id)->status);
    }

    public function test_a_stranger_cannot_agree_a_settlement(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();

        Sanctum::actingAs($this->someoneElse());

        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertNotFound();
    }

    /** Nobody ruled, so the session records the outcome with no arbitrator. */
    public function test_a_mutual_settlement_is_recorded_with_no_arbitrator(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->openDispute();
        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/disputes/{$id}/settlement")->assertOk();

        $session = \App\Models\ArbitrationSession::query()->where('dispute_id', $id)->firstOrFail();

        $this->assertSame('mutual_settlement', $session->outcome);
        $this->assertNull($session->arbitrator_id, 'the parties settled it themselves');
        $this->assertEqualsWithDelta(0.0, (float) $session->amount_to_client, 0.01, 'nothing moved between them');
    }

    public function test_the_reason_code_picker_is_served(): void
    {
        Sanctum::actingAs($this->client);

        $codes = $this->getJson('/api/v2/disputes/reason-codes')->assertOk()->json('data');

        $this->assertContains('not_delivered', $codes);
        $this->assertContains('other', $codes);
    }
}
