<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\ServiceRequest;
use App\Models\TripSchedule;
use App\Models\User;
use App\Services\Requests\RideRequestKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «استراحة» (owner, 2026-10-25): a limousine driver who wants a rest switches his requests off — no new request is sent
 * to him, he cannot answer one, and his «راجع فاضي» offers are not shown — until he switches back. The airports are
 * places like any city, so a ride (or a return) can start from the airport. Rolls back.
 */
class RequestsAvailabilityTest extends TestCase
{
    use DatabaseTransactions;

    private object $a;
    private object $b;

    protected function setUp(): void
    {
        parent::setUp();

        if (RideRequestKind::tradeId() <= 0) {
            $this->markTestSkipped('Needs the limousine trade.');
        }

        $cities = DB::table('cities')->whereNotNull('latitude')->orderBy('id')->limit(400)->get();
        $this->a = $cities->first();
        $this->b = $cities->first(fn ($c) => (int) $c->governorate_id !== (int) $this->a->governorate_id);
    }

    private function user(string $type, string $tag, array $extra = []): User
    {
        $u = new User();
        $u->forceFill($extra + [
            'name' => $tag . ' Name ' . Str::random(4),
            'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999),
            'password' => 'secret-password',
            'type' => $type,
            'api_token' => Str::random(80),
        ])->save();

        return $u;
    }

    private function driver(string $tag = 'drv'): User
    {
        return $this->user(User::TYPE_BUSINESS, $tag, [
            'category_id' => 5, 'category_child_id' => RideRequestKind::tradeId(),
            'governorate_id' => $this->a->governorate_id, 'city_id' => $this->a->id,
            'latitude' => $this->a->latitude, 'longitude' => $this->a->longitude,
        ]);
    }

    private function ride(): array
    {
        return [
            'kind' => 'ride',
            'origin' => ['governorate_id' => $this->a->governorate_id, 'city_id' => $this->a->id],
            'destination' => ['governorate_id' => $this->b->governorate_id, 'city_id' => $this->b->id],
            'passengers' => 1,
        ];
    }

    public function test_the_switch_reads_and_flips_and_a_default_driver_is_available(): void
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);

        $this->getJson('/api/v2/business/service-requests/availability')->assertOk()->assertJsonPath('data.available', true);
        $this->putJson('/api/v2/business/service-requests/availability', ['available' => false])->assertOk()->assertJsonPath('data.available', false);
        $this->assertNotNull($driver->fresh()->requests_paused_at);
        $this->getJson('/api/v2/business/service-requests/availability')->assertJsonPath('data.available', false);

        $this->putJson('/api/v2/business/service-requests/availability', ['available' => true])->assertOk()->assertJsonPath('data.available', true);
        $this->assertNull($driver->fresh()->requests_paused_at);
        $this->putJson('/api/v2/business/service-requests/availability', [])->assertStatus(422);
    }

    public function test_a_driver_on_a_break_is_not_told_of_a_new_request_and_cannot_answer_one(): void
    {
        $free = $this->driver('free');
        $resting = $this->driver('rest');
        $resting->forceFill(['requests_paused_at' => now()])->save();

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', $this->ride())->assertCreated()->json('data.request.id') ?? ServiceRequest::where('requester_id', $client->id)->value('id');

        $told = fn (User $u) => AppNotification::where('user_id', $u->id)->where('actor_id', $client->id)->exists();
        $this->assertTrue($told($free));
        $this->assertFalse($told($resting));

        Sanctum::actingAs($resting);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 100])->assertStatus(422)->assertJsonValidationErrors('request');

        // back from the break he may answer
        $this->putJson('/api/v2/business/service-requests/availability', ['available' => true])->assertOk();
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 100])->assertSuccessful();
    }

    public function test_a_driver_on_a_break_cannot_publish_a_return_and_the_ones_he_had_are_hidden_until_he_is_back(): void
    {
        if (! $this->b) {
            $this->markTestSkipped('Needs cities in two governorates.');
        }

        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $payload = ['origin_city_id' => $this->b->id, 'destination_city_id' => $this->a->id, 'departs_at' => now()->addHours(3)->format('Y-m-d H:i:s'), 'price' => 30];
        $id = $this->postJson('/api/v2/business/empty-returns', $payload)->assertCreated()->json('data.empty_return.id');

        $line = '/api/v2/empty-returns?origin_city_id=' . $this->b->id . '&destination_city_id=' . $this->a->id;
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));
        $this->assertNotNull(collect($this->getJson($line)->json('data.results'))->firstWhere('id', $id));

        $driver->forceFill(['requests_paused_at' => now()])->save();
        $this->assertNull(collect($this->getJson($line)->json('data.results'))->firstWhere('id', $id));

        Sanctum::actingAs($driver->fresh());
        $this->postJson('/api/v2/business/empty-returns', $payload + ['departs_at' => now()->addHours(5)->format('Y-m-d H:i:s')])
            ->assertStatus(422)->assertJsonValidationErrors('driver');

        $driver->forceFill(['requests_paused_at' => null])->save();
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust2'));
        $this->assertNotNull(collect($this->getJson($line)->json('data.results'))->firstWhere('id', $id));
    }

    public function test_the_main_airports_are_places_in_their_governorate(): void
    {
        $cairo = DB::table('cities')->where('name_ar', 'مطار القاهرة الدولي')->first();
        $this->assertNotNull($cairo);
        $this->assertSame(1, (int) $cairo->governorate_id);
        $this->assertNotNull(DB::table('cities')->where('name_ar', 'مطار برج العرب الدولي')->where('governorate_id', 3)->first());

        // a ride from the airport is priced from its coordinates like any other
        $damietta = DB::table('cities')->where('governorate_id', 10)->whereNotNull('latitude')->first();
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));
        $this->postJson('/api/v2/service-requests/quote', ['origin_city_id' => $cairo->id, 'destination_city_id' => $damietta->id])
            ->assertOk()->assertJsonPath('data.known', true);
    }

    public function test_a_reservation_names_its_line_its_day_and_its_carrier(): void
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/v2/business/empty-returns', [
            'origin_city_id' => $this->b->id, 'destination_city_id' => $this->a->id,
            'departs_at' => now()->addHours(4)->format('Y-m-d H:i:s'), 'price' => 30,
        ])->assertCreated()->json('data.empty_return.id');

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $this->postJson("/api/v2/schedules/{$id}/reserve", ['units' => 1])->assertCreated();

        $row = $this->getJson('/api/v2/schedules/my-reservations')->assertOk()->json('data.data.0');
        $this->assertSame($id, $row['leg']['id']);
        $this->assertTrue($row['leg']['is_return_leg']);
        $this->assertNotEmpty($row['leg']['origin']['city']);
        $this->assertNotEmpty($row['leg']['destination']['city']);
        $this->assertSame(TripSchedule::find($id)->trip_date->toDateString(), $row['leg']['trip_date']);
        $this->assertSame($driver->id, $row['business']['id']);

        // an action's answer keeps the line (the list replaces the row with it)
        Sanctum::actingAs($driver);
        $resId = $row['id'];
        $this->postJson("/api/v2/business/schedules/reservations/{$resId}/confirm")->assertOk()->assertJsonPath('data.reservation.leg.id', $id);

        // the carrier sees the same line on his side
        Sanctum::actingAs($driver);
        $mine = $this->getJson('/api/v2/business/schedules/reservations')->assertOk()->json('data.data.0');
        $this->assertSame($id, $mine['leg']['id']);
    }
}
