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

    public function test_the_reason_code_picker_is_served(): void
    {
        Sanctum::actingAs($this->client);

        $codes = $this->getJson('/api/v2/disputes/reason-codes')->assertOk()->json('data');

        $this->assertContains('not_delivered', $codes);
        $this->assertContains('other', $codes);
    }
}
