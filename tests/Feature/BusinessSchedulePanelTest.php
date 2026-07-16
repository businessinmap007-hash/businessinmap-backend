<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The carrier's web panel for the scheduling service: publishing trip legs and
 * working the reservation desk. Session-authenticated (the panel is behind
 * business.panel, not sanctum). Rolls back.
 */
class BusinessSchedulePanelTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $otherBusiness;
    private User $client;
    private int $cairo;
    private int $damietta;

    protected function setUp(): void
    {
        parent::setUp();

        $businesses = User::query()->where('type', 'business')->orderBy('id')->limit(2)->get();
        $client = User::query()->where('type', '!=', 'business')->first();
        $govs = Governorate::query()->orderBy('id')->limit(2)->pluck('id');

        if ($businesses->count() < 2 || ! $client || $govs->count() < 2) {
            $this->markTestSkipped('Needs 2 business users, a client and 2 governorates.');
        }

        $this->business = $businesses[0];
        $this->otherBusiness = $businesses[1];
        $this->client = $client;
        $this->cairo = (int) $govs[0];
        $this->damietta = (int) $govs[1];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mode' => TripSchedule::MODE_PASSENGER,
            'scope' => TripSchedule::SCOPE_DOMESTIC,
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'schedule_pattern' => TripSchedule::PATTERN_WEEKLY,
            'day_of_week' => 0,
            'departure_time' => '09:00',
            'capacity' => 14,
            'capacity_unit' => 'مقعد',
            'price' => 120,
            'currency' => 'EGP',
            'status' => TripSchedule::STATUS_ACTIVE,
        ], $overrides);
    }

    private function leg(User $owner, array $overrides = []): TripSchedule
    {
        return TripSchedule::create(array_merge($this->payload(), [
            'business_id' => (int) $owner->id,
        ], $overrides));
    }

    public function test_panel_is_closed_to_client_accounts(): void
    {
        $this->actingAs($this->client)
            ->get('/business/schedules')
            ->assertRedirect(route('business.login'));
    }

    public function test_carrier_publishes_a_leg_from_the_panel(): void
    {
        $res = $this->actingAs($this->business)
            ->post('/business/schedules', $this->payload());

        $res->assertRedirect(route('business.schedules.index'));
        $res->assertSessionHas('success');

        $this->assertDatabaseHas('trip_schedules', [
            'business_id' => (int) $this->business->id,
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'mode' => TripSchedule::MODE_PASSENGER,
            'capacity' => 14,
        ]);
    }

    public function test_publish_rejects_same_origin_and_destination(): void
    {
        $this->actingAs($this->business)
            ->post('/business/schedules', $this->payload([
                'destination_governorate_id' => $this->cairo,
            ]))
            ->assertSessionHasErrors('destination_governorate_id');

        $this->assertDatabaseMissing('trip_schedules', [
            'business_id' => (int) $this->business->id,
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->cairo,
        ]);
    }

    public function test_weekly_leg_requires_a_day(): void
    {
        $this->actingAs($this->business)
            ->post('/business/schedules', $this->payload(['day_of_week' => null]))
            ->assertSessionHasErrors('day_of_week');
    }

    public function test_index_lists_only_the_owners_own_legs(): void
    {
        $mine = $this->leg($this->business);
        $theirs = $this->leg($this->otherBusiness);

        $res = $this->actingAs($this->business)->get('/business/schedules');

        $res->assertOk();
        $rows = $res->viewData('rows')->pluck('id')->all();

        $this->assertContains($mine->id, $rows);
        $this->assertNotContains($theirs->id, $rows, 'the panel must never surface another carrier\'s legs');
    }

    public function test_carrier_cannot_edit_another_carriers_leg(): void
    {
        $theirs = $this->leg($this->otherBusiness);

        $this->actingAs($this->business)
            ->get("/business/schedules/{$theirs->id}/edit")
            ->assertNotFound();

        $this->actingAs($this->business)
            ->put("/business/schedules/{$theirs->id}", $this->payload())
            ->assertNotFound();

        $this->actingAs($this->business)
            ->delete("/business/schedules/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_carrier_updates_and_deletes_own_leg(): void
    {
        $leg = $this->leg($this->business);

        $this->actingAs($this->business)
            ->put("/business/schedules/{$leg->id}", $this->payload(['capacity' => 7]))
            ->assertRedirect(route('business.schedules.index'));

        $this->assertSame(7, (int) $leg->fresh()->capacity);

        $this->actingAs($this->business)
            ->delete("/business/schedules/{$leg->id}")
            ->assertRedirect(route('business.schedules.index'));

        $this->assertDatabaseMissing('trip_schedules', ['id' => $leg->id]);
    }

    public function test_index_shows_capacity_left_after_a_hold(): void
    {
        $leg = $this->leg($this->business, ['capacity' => 10]);

        TripReservation::create([
            'trip_schedule_id' => (int) $leg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 4,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_CONFIRMED,
        ]);

        $res = $this->actingAs($this->business)->get('/business/schedules');

        $res->assertOk();
        $this->assertSame(6, $res->viewData('remaining')[$leg->id], '10 capacity minus a 4-unit hold');
    }

    public function test_offline_block_holds_capacity_and_reject_releases_it(): void
    {
        $leg = $this->leg($this->business, ['capacity' => 14]);

        $this->actingAs($this->business)
            ->post("/business/schedules/{$leg->id}/block", ['units' => 7])
            ->assertSessionHas('success');

        $hold = TripReservation::query()
            ->where('trip_schedule_id', $leg->id)
            ->where('source', TripReservation::SOURCE_OFFLINE)
            ->firstOrFail();

        $this->assertSame(TripReservation::STATUS_BLOCKED, $hold->status);
        $this->assertNull($hold->client_id, 'an offline hold has no in-app client');

        $res = $this->actingAs($this->business)->get('/business/schedules');
        $this->assertSame(7, $res->viewData('remaining')[$leg->id]);

        // Releasing the hold gives the seats back.
        $this->actingAs($this->business)
            ->post("/business/schedules/reservations/{$hold->id}/reject")
            ->assertSessionHas('success');

        $this->assertSame(TripReservation::STATUS_CANCELLED, $hold->fresh()->status);

        $res = $this->actingAs($this->business)->get('/business/schedules');
        $this->assertSame(14, $res->viewData('remaining')[$leg->id]);
    }

    public function test_carrier_cannot_block_capacity_on_another_carriers_leg(): void
    {
        $theirs = $this->leg($this->otherBusiness);

        $this->actingAs($this->business)
            ->post("/business/schedules/{$theirs->id}/block", ['units' => 2])
            ->assertNotFound();
    }

    public function test_reservation_desk_confirms_then_completes(): void
    {
        $leg = $this->leg($this->business);

        $reservation = TripReservation::create([
            'trip_schedule_id' => (int) $leg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 1,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_PENDING,
        ]);

        $this->actingAs($this->business)
            ->post("/business/schedules/reservations/{$reservation->id}/confirm")
            ->assertSessionHas('success');

        $this->assertSame(TripReservation::STATUS_CONFIRMED, $reservation->fresh()->status);

        $this->actingAs($this->business)
            ->post("/business/schedules/reservations/{$reservation->id}/complete")
            ->assertSessionHas('success');

        $this->assertSame(TripReservation::STATUS_COMPLETED, $reservation->fresh()->status);
    }

    public function test_desk_refuses_completing_an_unconfirmed_reservation(): void
    {
        $leg = $this->leg($this->business);

        $reservation = TripReservation::create([
            'trip_schedule_id' => (int) $leg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 1,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_PENDING,
        ]);

        $this->actingAs($this->business)
            ->post("/business/schedules/reservations/{$reservation->id}/complete")
            ->assertSessionHasErrors('status');

        $this->assertSame(TripReservation::STATUS_PENDING, $reservation->fresh()->status);
    }

    public function test_carrier_cannot_act_on_another_carriers_reservation(): void
    {
        $theirs = $this->leg($this->otherBusiness);

        $reservation = TripReservation::create([
            'trip_schedule_id' => (int) $theirs->id,
            'business_id' => (int) $this->otherBusiness->id,
            'client_id' => (int) $this->client->id,
            'units' => 1,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_PENDING,
        ]);

        $this->actingAs($this->business)
            ->post("/business/schedules/reservations/{$reservation->id}/confirm")
            ->assertNotFound();

        $this->assertSame(TripReservation::STATUS_PENDING, $reservation->fresh()->status);
    }

    public function test_reservation_desk_lists_only_own_reservations(): void
    {
        $mineLeg = $this->leg($this->business);
        $theirLeg = $this->leg($this->otherBusiness);

        $mine = TripReservation::create([
            'trip_schedule_id' => (int) $mineLeg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 1,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_PENDING,
        ]);

        $theirs = TripReservation::create([
            'trip_schedule_id' => (int) $theirLeg->id,
            'business_id' => (int) $this->otherBusiness->id,
            'client_id' => (int) $this->client->id,
            'units' => 1,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_PENDING,
        ]);

        $res = $this->actingAs($this->business)->get('/business/schedules/reservations');

        $res->assertOk();
        $ids = $res->viewData('rows')->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_publish_form_renders_with_its_pickers(): void
    {
        $res = $this->actingAs($this->business)->get('/business/schedules/create');

        $res->assertOk();
        $res->assertSee('نشر خط تشغيل');

        // The vehicle picker is keyed by mode; the form narrows it client-side.
        $byMode = $res->viewData('vehicleTypesByMode');
        $this->assertArrayHasKey(TripSchedule::MODE_PASSENGER, $byMode);
        $this->assertArrayHasKey(TripSchedule::MODE_FREIGHT, $byMode);
    }
}
