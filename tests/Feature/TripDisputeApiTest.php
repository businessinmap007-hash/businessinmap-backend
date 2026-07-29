<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\RatingOutcomeEvent;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserOperationRating;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The schedules-service dispute door. A trip reservation is rated on
 * completion/cancellation like an order, but had no way to open a dispute — so
 * an OUTCOME_DISPUTED for a trip never reached the rating ledger. This walks
 * that door and proves the outcome is recorded for both parties.
 */
class TripDisputeApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;
    private TripReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->client = User::query()
            ->where('type', '!=', 'business')
            ->where('id', '!=', $this->business->id)
            ->orderBy('id')->firstOrFail();

        $schedule = TripSchedule::create([
            'business_id' => $this->business->id,
            'mode' => 'freight',
            'scope' => 'domestic',
            'schedule_pattern' => 'one_off',
            'capacity' => 10,
            'capacity_unit' => 'unit',
            'price' => 100,
            'currency' => 'EGP',
            'status' => 'active',
        ]);

        $this->reservation = TripReservation::create([
            'trip_schedule_id' => $schedule->id,
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'units' => 1,
            'unit_price' => 100,
            'total_price' => 100,
            'currency' => 'EGP',
            'source' => 'app',
            'status' => TripReservation::STATUS_CONFIRMED,
        ]);
    }

    private function url(?int $id = null): string
    {
        return "/api/v2/schedules/reservations/" . ($id ?? $this->reservation->id) . "/disputes";
    }

    private function someoneElse(): User
    {
        return User::query()
            ->whereNotIn('id', [(int) $this->client->id, (int) $this->business->id])
            ->orderBy('id')->firstOrFail();
    }

    private function disputedCount(int $userId, string $role): int
    {
        return (int) (UserOperationRating::query()
            ->where('user_id', $userId)->where('role', $role)
            ->value('disputed_count') ?? 0);
    }

    public function test_the_client_can_open_a_dispute_on_their_reservation(): void
    {
        Sanctum::actingAs($this->client);

        $this->postJson($this->url(), ['reason_code' => 'not_delivered'])
            ->assertOk()
            ->assertJsonPath('data.my_role', 'opener')
            ->assertJsonPath('data.operation.kind', 'trip')
            ->assertJsonPath('data.operation.id', $this->reservation->id);

        $this->assertDatabaseHas('disputes', [
            'disputeable_type' => TripReservation::class,
            'disputeable_id' => $this->reservation->id,
            'type' => 'trip',
            'opened_by_user_id' => $this->client->id,
            'against_user_id' => $this->business->id,
        ]);
    }

    public function test_opening_records_the_disputed_outcome_for_both_parties(): void
    {
        $bizBefore = $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);
        $cliBefore = $this->disputedCount((int) $this->client->id, UserOperationRating::ROLE_CLIENT);

        Sanctum::actingAs($this->client);
        $this->postJson($this->url(), ['reason_code' => 'quality'])->assertOk();

        $this->assertSame($bizBefore + 1, $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS));
        $this->assertSame($cliBefore + 1, $this->disputedCount((int) $this->client->id, UserOperationRating::ROLE_CLIENT));

        $this->assertDatabaseHas('rating_outcome_events', [
            'operation_type' => TripReservation::OP_TRIP,
            'operation_id' => $this->reservation->id,
            'outcome' => RatingOutcomeEvent::OUTCOME_DISPUTED,
        ]);
    }

    public function test_the_business_can_open_a_dispute(): void
    {
        Sanctum::actingAs($this->business);

        $this->postJson($this->url(), ['reason_code' => 'no_show'])
            ->assertOk()
            ->assertJsonPath('data.my_role', 'opener');

        $this->assertDatabaseHas('disputes', [
            'disputeable_id' => $this->reservation->id,
            'opened_by_user_id' => $this->business->id,
            'against_user_id' => $this->client->id,
        ]);
    }

    public function test_a_stranger_cannot_open_a_dispute(): void
    {
        Sanctum::actingAs($this->someoneElse());

        $this->postJson($this->url(), ['reason_code' => 'quality'])->assertNotFound();

        $this->assertDatabaseMissing('disputes', [
            'disputeable_type' => TripReservation::class,
            'disputeable_id' => $this->reservation->id,
        ]);
    }

    public function test_opening_requires_authentication(): void
    {
        $this->postJson($this->url(), ['reason_code' => 'quality'])->assertUnauthorized();
    }

    public function test_a_blocked_carrier_hold_cannot_be_disputed(): void
    {
        $this->reservation->update(['status' => TripReservation::STATUS_BLOCKED, 'client_id' => null]);

        Sanctum::actingAs($this->business);
        $this->postJson($this->url(), ['reason_code' => 'quality'])->assertNotFound();
    }

    public function test_opening_twice_returns_one_dispute_and_counts_once(): void
    {
        $bizBefore = $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS);

        Sanctum::actingAs($this->client);
        $first = $this->postJson($this->url(), ['reason_code' => 'late']);
        $second = $this->postJson($this->url(), ['reason_code' => 'quality']);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(
            1,
            Dispute::query()->where('disputeable_type', TripReservation::class)
                ->where('disputeable_id', $this->reservation->id)->count()
        );
        $this->assertSame($bizBefore + 1, $this->disputedCount((int) $this->business->id, UserOperationRating::ROLE_BUSINESS));
    }
}
