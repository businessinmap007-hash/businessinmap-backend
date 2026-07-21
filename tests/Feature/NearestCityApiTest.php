<?php

namespace Tests\Feature;

use App\Models\City;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Use my location" — GET /api/v2/locations/nearest resolves a GPS point to one
 * of our own cities, with no map provider or third-party geocoder involved. The
 * tests pin: a point on a city resolves to that city, a point far out at sea
 * returns no confident match, and it is reachable without a token (the picker
 * runs at registration, before there is a session).
 */
class NearestCityApiTest extends TestCase
{
    use DatabaseTransactions;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $city = City::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('latitude', '!=', 0)->where('longitude', '!=', 0)
            ->whereHas('governorate')
            ->first();

        if (! $city) {
            $this->markTestSkipped('Needs a city with coordinates and a governorate.');
        }

        $this->city = $city;
    }

    public function test_a_point_on_a_city_resolves_to_that_city(): void
    {
        $res = $this->getJson("/api/v2/locations/nearest?lat={$this->city->latitude}&lng={$this->city->longitude}")
            ->assertOk();

        $this->assertSame((int) $this->city->id, (int) $res->json('data.match.city.id'));
        $this->assertSame((int) $this->city->governorate_id, (int) $res->json('data.match.governorate.id'));
        $this->assertNotNull($res->json('data.match.country_id'));
        // Standing on the city, the distance is effectively zero.
        $this->assertLessThan(1.0, (float) $res->json('data.match.distance_km'));
    }

    public function test_a_nearby_point_still_finds_the_city(): void
    {
        // ~2 km north — still comfortably inside the cap.
        $lat = (float) $this->city->latitude + 0.018;
        $lng = (float) $this->city->longitude;

        $res = $this->getJson("/api/v2/locations/nearest?lat={$lat}&lng={$lng}")->assertOk();

        $this->assertNotNull($res->json('data.match'), 'a point a couple km away must still match');
    }

    public function test_a_point_in_the_open_ocean_has_no_confident_match(): void
    {
        // Middle of the South Atlantic — nothing in the tables is within the cap.
        $res = $this->getJson('/api/v2/locations/nearest?lat=-40.0&lng=-30.0')->assertOk();

        $this->assertNull($res->json('data.match'));
    }

    public function test_it_validates_the_coordinates(): void
    {
        $this->getJson('/api/v2/locations/nearest?lat=999&lng=0')->assertStatus(422);
        $this->getJson('/api/v2/locations/nearest?lat=30')->assertStatus(422);
    }

    public function test_it_is_public(): void
    {
        // No Authorization header — the picker runs before registration.
        $this->getJson("/api/v2/locations/nearest?lat={$this->city->latitude}&lng={$this->city->longitude}")
            ->assertOk();
    }
}
