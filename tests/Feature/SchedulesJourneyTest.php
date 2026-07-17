<?php

namespace Tests\Feature;

use App\Models\TripReservation;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The scheduling / routes service, walked by both sides.
 *
 * `trip_schedules` and `trip_reservations` both hold zero rows — the service has
 * never actually run. This walks it: a carrier publishes "we run this route on
 * this day", a passenger searches the route and rides it.
 *
 * Unlike the other journeys, the carrier's setup is done through the API too,
 * because publishing a leg IS a carrier's journey — they are an app user, not a
 * back-office fixture. So the rule applies to both actors: every id either side
 * uses comes out of a previous API RESPONSE. That includes the geography — the
 * governorate ids come from /locations, the way the app's pickers get them,
 * which is exactly the hop that BIM-11.1 proved nobody had ever walked.
 *
 * Rolls back.
 */
class SchedulesJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'secret-password';

    /** Sunday. The service derives the weekday with date('w'), so 0 = Sunday. */
    private const SUNDAY = 0;

    /**
     * Laravel caches the resolved user for the whole test method — swapping the
     * Bearer header does NOT re-authenticate. See MenuOrderJourneyTest.
     */
    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function makeUser(string $name, string $type = User::TYPE_CLIENT): User
    {
        $user = new User();
        $user->name = $name;
        $user->email = 'trip-' . uniqid() . '@example.test';
        $user->phone = '0109' . random_int(1000000, 9999999);
        $user->password = self::PASSWORD;
        $user->type = $type;
        $user->api_token = Str::random(80);
        $user->save();

        return $user->fresh();
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }

    /**
     * Two governorate ids, discovered the way the app's pickers discover them:
     * countries → governorates. Never lifted out of the table.
     *
     * @return array{0:int,1:int}
     */
    private function aRouteTheAppCanSee(): array
    {
        $countries = $this->getJson('/api/v2/locations/countries')->assertOk()->json('data.countries');
        $this->assertNotEmpty($countries);

        // Find Egypt the way a picker would — by the ISO code the API returns.
        // Deliberately no "?? $countries[0]" fallback: a fallback here would
        // quietly pick a country with no governorates and turn a real failure
        // into a skip. That is precisely how AddressApiTest stayed green while
        // the address book was broken.
        $egypt = collect($countries)->firstWhere('iso2', 'EG');
        $this->assertNotNull($egypt, 'the country list must contain Egypt — all 27 governorates hang off it');

        $governorates = $this->getJson('/api/v2/locations/governorates?country_id=' . $egypt['id'])
            ->assertOk()
            ->json('data.governorates');

        $this->assertGreaterThanOrEqual(
            2,
            count($governorates),
            'a country whose governorates the app cannot list has no routes to sell'
        );

        return [(int) $governorates[0]['id'], (int) $governorates[1]['id']];
    }

    /**
     * A carrier publishes one weekly leg, through the API, and returns
     * [carrierToken, carrier, scheduleId, origin, destination].
     *
     * @return array{0:string,1:User,2:int,3:int,4:int}
     */
    private function aPublishedLeg(array $overrides = []): array
    {
        [$origin, $destination] = $this->aRouteTheAppCanSee();

        $carrier = $this->makeUser('شركة النقل', User::TYPE_BUSINESS);
        $token = $this->tokenFor($carrier);

        // The carrier's vehicle picker. The id must come from here — a class
        // belonging to another service is rejected on purpose.
        $vehicleTypes = $this->actingWithToken($token)
            ->getJson('/api/v2/schedules/vehicle-types?mode=passenger')
            ->assertOk()
            ->json('data.vehicle_types');

        $this->assertNotEmpty($vehicleTypes, 'a carrier with no vehicle classes to pick from cannot publish');

        $schedule = $this->actingWithToken($token)->postJson('/api/v2/business/schedules', array_merge([
            'mode' => 'passenger',
            'vehicle_type_id' => $vehicleTypes[0]['id'],
            'vehicle_label' => 'ميكروباص ٢٠٢٣',
            'origin_governorate_id' => $origin,
            'destination_governorate_id' => $destination,
            'schedule_pattern' => 'weekly',
            'day_of_week' => self::SUNDAY,
            'departure_time' => '08:00',
            'capacity' => 14,
            'capacity_unit' => 'seat',
            'price' => 75,
        ], $overrides))->assertCreated()->json('data.schedule');

        return [$token, $carrier, (int) $schedule['id'], $origin, $destination];
    }

    public function test_a_carrier_publishes_a_route_and_a_passenger_rides_it(): void
    {
        [$carrierToken, $carrier, $scheduleId, $origin, $destination] = $this->aPublishedLeg();

        $passenger = $this->makeUser('راكب');
        $passengerToken = $this->tokenFor($passenger);

        // ── The passenger searches the route, holding only ids the app gave them.
        $results = $this->getJson('/api/v2/search/schedules?' . http_build_query([
            'origin_governorate_id' => $origin,
            'destination_governorate_id' => $destination,
            'day_of_week' => self::SUNDAY,
        ]))->assertOk()->json('data.results');

        $found = collect($results)->firstWhere('schedule.id', $scheduleId);

        $this->assertNotNull($found, 'a published leg nobody can search for is not published');
        $this->assertSame(14, $found['remaining_capacity'], 'an empty bus must show all its seats');
        $this->assertArrayHasKey('trust', $found, 'the whole point of the service is ranking carriers by trust');
        $this->assertSame(75.0, (float) $found['schedule']['price']);

        // ── Reserve two seats.
        $reservation = $this->actingWithToken($passengerToken)
            ->postJson('/api/v2/schedules/' . $found['schedule']['id'] . '/reserve', ['units' => 2])
            ->assertCreated()
            ->json('data.reservation');

        $this->assertSame(TripReservation::STATUS_PENDING, $reservation['status']);
        $this->assertSame(150.0, (float) $reservation['total_price'], 'two seats at 75 is 150');

        // ── The seats are gone from the route the moment they are held, or the
        // next passenger buys a seat that does not exist.
        $afterBooking = collect($this->getJson('/api/v2/search/schedules?' . http_build_query([
            'origin_governorate_id' => $origin,
            'destination_governorate_id' => $destination,
            'day_of_week' => self::SUNDAY,
        ]))->assertOk()->json('data.results'))->firstWhere('schedule.id', $scheduleId);

        $this->assertSame(12, $afterBooking['remaining_capacity']);

        // ── The carrier sees the request and takes it.
        $incoming = $this->actingWithToken($carrierToken)
            ->getJson('/api/v2/business/schedules/reservations')
            ->assertOk()
            ->json('data.data');

        $this->assertContains(
            (int) $reservation['id'],
            array_column($incoming, 'id'),
            'a reservation the carrier cannot see can never be confirmed'
        );

        $this->actingWithToken($carrierToken)
            ->postJson('/api/v2/business/schedules/reservations/' . $reservation['id'] . '/confirm')
            ->assertOk()
            ->assertJsonPath('data.reservation.status', TripReservation::STATUS_CONFIRMED);

        // ── The trip runs.
        $this->actingWithToken($carrierToken)
            ->postJson('/api/v2/business/schedules/reservations/' . $reservation['id'] . '/complete')
            ->assertOk()
            ->assertJsonPath('data.reservation.status', TripReservation::STATUS_COMPLETED);

        // ── The passenger sees it in their own list.
        $mine = $this->actingWithToken($passengerToken)
            ->getJson('/api/v2/schedules/my-reservations')
            ->assertOk()
            ->json('data.data');

        $this->assertSame(TripReservation::STATUS_COMPLETED, collect($mine)->firstWhere('id', $reservation['id'])['status']);

        // ── A completed trip is a recorded success for BOTH sides. This is what
        // the trust ranking above is built out of, so if it is not ledgered the
        // service has no memory.
        $this->assertDatabaseHas('user_operation_ratings', [
            'user_id' => $carrier->id,
            'role' => UserOperationRating::ROLE_BUSINESS,
        ]);

        $carrierRating = UserOperationRating::query()
            ->where('user_id', $carrier->id)
            ->where('role', UserOperationRating::ROLE_BUSINESS)
            ->first();

        $this->assertSame(1, (int) $carrierRating->total_operations);
        $this->assertSame(100.0, (float) $carrierRating->successRate());
    }

    public function test_a_bus_cannot_be_oversold(): void
    {
        [, , $scheduleId] = $this->aPublishedLeg(['capacity' => 3]);

        $first = $this->tokenFor($this->makeUser('راكب أول'));
        $this->actingWithToken($first)
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 2])
            ->assertCreated();

        // One seat left, two asked for.
        $this->actingWithToken($this->tokenFor($this->makeUser('راكب ثانٍ')))
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 2])
            ->assertStatus(422);

        // The last seat is still sellable — the refusal must be about capacity,
        // not a leg that quietly closed.
        $this->actingWithToken($this->tokenFor($this->makeUser('راكب ثالث')))
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 1])
            ->assertCreated();
    }

    public function test_a_cancelled_seat_goes_back_on_sale(): void
    {
        [, , $scheduleId, $origin, $destination] = $this->aPublishedLeg(['capacity' => 2]);

        $passengerToken = $this->tokenFor($this->makeUser('راكب متردد'));

        $reservation = $this->actingWithToken($passengerToken)
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 2])
            ->assertCreated()
            ->json('data.reservation');

        $this->actingWithToken($passengerToken)
            ->postJson('/api/v2/schedules/reservations/' . $reservation['id'] . '/cancel')
            ->assertOk();

        $row = collect($this->getJson('/api/v2/search/schedules?' . http_build_query([
            'origin_governorate_id' => $origin,
            'destination_governorate_id' => $destination,
            'day_of_week' => self::SUNDAY,
        ]))->assertOk()->json('data.results'))->firstWhere('schedule.id', $scheduleId);

        $this->assertSame(2, $row['remaining_capacity'], 'a cancelled seat that never returns to the pool is a seat sold to nobody');
    }

    public function test_a_deposit_is_held_from_the_wallet_and_returned_when_the_trip_is_cancelled(): void
    {
        [, , $scheduleId] = $this->aPublishedLeg(['deposit_per_unit' => 50, 'capacity' => 4]);

        $passenger = $this->makeUser('راكب بعربون');
        $passengerToken = $this->tokenFor($passenger);

        app(WalletService::class)->deposit(
            userId: (int) $passenger->id,
            amount: 200,
            note: 'رصيد اختبار',
            idempotencyKey: 'trip_journey_seed_' . $passenger->id
        );

        $reservation = $this->actingWithToken($passengerToken)
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 2])
            ->assertCreated()
            ->json('data.reservation');

        $this->assertSame(100.0, (float) $reservation['deposit_held'], 'two seats at 50 deposit each');

        $wallet = Wallet::query()->where('user_id', $passenger->id)->first();
        $this->assertSame(100.0, (float) $wallet->balance, 'the deposit must leave the spendable balance');
        $this->assertSame(100.0, (float) $wallet->locked_balance, 'and be genuinely locked, not just deducted');

        $this->actingWithToken($passengerToken)
            ->postJson('/api/v2/schedules/reservations/' . $reservation['id'] . '/cancel')
            ->assertOk();

        $wallet->refresh();
        $this->assertSame(200.0, (float) $wallet->balance, 'a cancelled trip must give the money back');
        $this->assertSame(0.0, (float) $wallet->locked_balance);
    }

    public function test_a_deposit_the_passenger_cannot_afford_books_nothing_at_all(): void
    {
        [, , $scheduleId, $origin, $destination] = $this->aPublishedLeg(['deposit_per_unit' => 500, 'capacity' => 4]);

        $passengerToken = $this->tokenFor($this->makeUser('راكب مفلس'));

        $this->actingWithToken($passengerToken)
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 1])
            ->assertStatus(422);

        // The reservation and the held seat are written before the deposit is
        // taken, so a failed hold MUST roll both back — otherwise an empty wallet
        // silently holds a seat forever.
        $this->assertSame(
            0,
            TripReservation::query()->where('trip_schedule_id', $scheduleId)->count(),
            'a reservation that could not be paid for must not survive'
        );

        $row = collect($this->getJson('/api/v2/search/schedules?' . http_build_query([
            'origin_governorate_id' => $origin,
            'destination_governorate_id' => $destination,
            'day_of_week' => self::SUNDAY,
        ]))->assertOk()->json('data.results'))->firstWhere('schedule.id', $scheduleId);

        $this->assertSame(4, $row['remaining_capacity'], 'a seat must not be stranded by a failed payment');
    }

    public function test_a_carrier_cannot_ride_their_own_bus(): void
    {
        [$carrierToken, , $scheduleId] = $this->aPublishedLeg();

        $this->actingWithToken($carrierToken)
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 1])
            ->assertStatus(422);
    }

    public function test_a_paused_leg_is_neither_searchable_nor_bookable(): void
    {
        [$carrierToken, , $scheduleId, $origin, $destination] = $this->aPublishedLeg();

        $this->actingWithToken($carrierToken)
            ->patchJson('/api/v2/business/schedules/' . $scheduleId, [
                'mode' => 'passenger',
                'origin_governorate_id' => $origin,
                'destination_governorate_id' => $destination,
                'schedule_pattern' => 'weekly',
                'day_of_week' => self::SUNDAY,
                'status' => 'paused',
            ])->assertOk();

        $results = $this->getJson('/api/v2/search/schedules?' . http_build_query([
            'origin_governorate_id' => $origin,
            'destination_governorate_id' => $destination,
            'day_of_week' => self::SUNDAY,
        ]))->assertOk()->json('data.results');

        $this->assertNull(collect($results)->firstWhere('schedule.id', $scheduleId), 'a paused leg must leave the search');

        // And a passenger holding the old id must still be refused.
        $this->actingWithToken($this->tokenFor($this->makeUser('راكب متأخر')))
            ->postJson('/api/v2/schedules/' . $scheduleId . '/reserve', ['units' => 1])
            ->assertStatus(422);
    }

    public function test_publishing_a_route_is_business_only(): void
    {
        [$origin, $destination] = $this->aRouteTheAppCanSee();

        $this->actingWithToken($this->tokenFor($this->makeUser('عميل عادي')))
            ->postJson('/api/v2/business/schedules', [
                'mode' => 'passenger',
                'origin_governorate_id' => $origin,
                'destination_governorate_id' => $destination,
                'schedule_pattern' => 'weekly',
                'day_of_week' => self::SUNDAY,
            ])->assertForbidden();
    }

    public function test_a_search_with_only_half_a_route_is_refused(): void
    {
        [$origin] = $this->aRouteTheAppCanSee();

        // "Who leaves Cairo?" is not a route. Answering it would mean scanning
        // every leg in the country.
        $this->getJson('/api/v2/search/schedules?origin_governorate_id=' . $origin)
            ->assertStatus(422);
    }
}
