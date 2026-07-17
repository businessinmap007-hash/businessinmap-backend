<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BIM-11.1 — the geography pickers.
 *
 * These exist because POST /api/v2/addresses required governorate_id and
 * city_id while v2 offered no way to learn a single valid id. Rolls back.
 */
class LocationApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_countries_come_from_the_populated_iso_table_not_the_dead_tree(): void
    {
        $response = $this->getJson('/api/v2/locations/countries')->assertOk();

        $countries = $response->json('data.countries');

        // `locations` holds 71 country rows whose names are ALL empty; the ISO
        // table holds 249 with names and flags. This is the difference.
        $this->assertGreaterThan(200, count($countries));
        $this->assertNotEmpty($countries[0]['name_ar'], 'a picker of blank names is not a picker');
    }

    public function test_the_pickers_are_public_because_an_address_is_chosen_before_login(): void
    {
        $this->getJson('/api/v2/locations/countries')->assertOk();
        $this->getJson('/api/v2/locations/governorates?country_id=' . $this->egypt()->id)->assertOk();
    }

    public function test_countries_can_be_searched(): void
    {
        $response = $this->getJson('/api/v2/locations/countries?q=Egypt')->assertOk();

        $names = array_column($response->json('data.countries'), 'name_en');

        $this->assertContains('Egypt', $names);
        $this->assertLessThan(20, count($names), 'a search that returns everything is not a search');
    }

    public function test_governorates_are_scoped_to_their_country(): void
    {
        $egypt = $this->egypt();

        $response = $this->getJson('/api/v2/locations/governorates?country_id=' . $egypt->id)->assertOk();

        $governorates = $response->json('data.governorates');

        $this->assertNotEmpty($governorates);

        foreach ($governorates as $governorate) {
            $this->assertSame((int) $egypt->id, $governorate['country_id']);
        }
    }

    public function test_governorates_refuse_to_guess_a_country(): void
    {
        // A silent default returns a plausible list for the wrong country and
        // the caller cannot tell.
        $this->getJson('/api/v2/locations/governorates')
            ->assertStatus(422)
            ->assertJsonValidationErrors('country_id');

        $this->getJson('/api/v2/locations/governorates?country_id=999999')
            ->assertStatus(422);
    }

    public function test_cities_are_scoped_to_their_governorate(): void
    {
        $governorate = Governorate::query()->where('country_id', $this->egypt()->id)->first();

        $response = $this->getJson('/api/v2/locations/cities?governorate_id=' . $governorate->id)->assertOk();

        $cities = $response->json('data.cities');

        $this->assertNotEmpty($cities);

        foreach ($cities as $city) {
            $this->assertSame((int) $governorate->id, $city['governorate_id']);
        }
    }

    public function test_city_search_carries_the_parent_so_a_form_can_fill_itself_in(): void
    {
        $city = City::query()->whereNotNull('governorate_id')->whereNotNull('name_ar')->first();

        $response = $this->getJson('/api/v2/locations/cities/search?q=' . urlencode(mb_substr($city->name_ar, 0, 3)))
            ->assertOk();

        $rows = $response->json('data.cities');

        $this->assertNotEmpty($rows);
        // "I know the town, not the governorate" — the parent comes back with it.
        $this->assertArrayHasKey('governorate', $rows[0]);
        $this->assertNotNull($rows[0]['governorate']);
        $this->assertArrayHasKey('country_id', $rows[0]['governorate']);
    }

    public function test_the_legacy_schedules_countries_path_still_works(): void
    {
        // Same list, one implementation — the old path must not break.
        $this->getJson('/api/v2/schedules/countries')
            ->assertOk()
            ->assertJsonStructure(['data' => ['countries']]);
    }

    private function egypt(): Country
    {
        return Country::query()->where('name_en', 'Egypt')->firstOrFail();
    }
}
