<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BIM-11.1 — the v2 address book, which until now could not create an address.
 *
 * governorate_id and city_id were validated against `locations`: a tree holding
 * 71 country rows and no governorates or cities at all. So القاهرة
 * (governorates.id = 1) was rejected because locations has no id 1, while
 * governorate_id = 2 "passed" by matching a COUNTRY. The addresses table had
 * zero rows, and without an address there is no delivery. Rolls back.
 */
class AddressBookTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(): User
    {
        $user = new User();
        $user->name = 'Address Test';
        $user->email = 'addr-' . uniqid() . '@example.test';
        $user->phone = '0128' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = User::TYPE_CLIENT;
        $user->api_token = Str::random(80);
        $user->save();

        return $user->fresh();
    }

    private function egypt(): Country
    {
        return Country::query()->where('name_en', 'Egypt')->firstOrFail();
    }

    /** A governorate with at least one city, and one of its cities. */
    private function place(int $skip = 0): array
    {
        $governorate = Governorate::query()
            ->where('country_id', $this->egypt()->id)
            ->whereHas('cities')
            ->skip($skip)->take(1)->first();

        $city = City::query()->where('governorate_id', $governorate->id)->firstOrFail();

        return [$governorate, $city];
    }

    public function test_an_address_can_finally_be_created(): void
    {
        [$governorate, $city] = $this->place();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'address_line' => 'شارع التحرير، عمارة 12',
        ])
            ->assertCreated()
            ->assertJsonPath('data.governorate_id', (int) $governorate->id)
            ->assertJsonPath('data.city_id', (int) $city->id)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
        ]);
    }

    public function test_cairo_is_no_longer_rejected(): void
    {
        // The exact failure: governorates.id = 1 with no locations.id = 1.
        $cairo = Governorate::query()->where('name_ar', 'القاهرة')->first();

        if (! $cairo) {
            $this->markTestSkipped('Needs the القاهرة governorate.');
        }

        $city = City::query()->where('governorate_id', $cairo->id)->first();

        if (! $city) {
            $this->markTestSkipped('Needs a city in القاهرة.');
        }

        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $cairo->id,
            'city_id' => $city->id,
            'address_line' => 'وسط البلد، شارع طلعت حرب',
        ])->assertCreated();
    }

    public function test_the_country_is_derived_rather_than_asked_for(): void
    {
        [$governorate, $city] = $this->place();
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'address_line' => 'شارع بلا دولة مذكورة',
        ])
            ->assertCreated()
            // The governorate already knows its country; asking is an invitation
            // to disagree with it.
            ->assertJsonPath('data.country_id', (int) $governorate->country_id);
    }

    public function test_a_city_from_another_governorate_is_refused(): void
    {
        // Three ids that each exist are still not an address: this pairing would
        // send a driver somewhere the customer never chose.
        [$governorate] = $this->place(0);
        [, $otherCity] = $this->place(1);

        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $governorate->id,
            'city_id' => $otherCity->id,
            'address_line' => 'عنوان مستحيل جغرافيًا',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');
    }

    public function test_a_governorate_from_another_country_is_refused(): void
    {
        [$governorate, $city] = $this->place();
        $otherCountry = Country::query()->where('id', '!=', $governorate->country_id)->firstOrFail();

        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v2/addresses', [
            'country_id' => $otherCountry->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'address_line' => 'محافظة مصرية في دولة أخرى',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('governorate_id');
    }

    public function test_ids_from_the_dead_locations_tree_are_now_refused(): void
    {
        // locations.id = 2 is a COUNTRY and used to pass as a governorate.
        Sanctum::actingAs($this->makeUser());

        $bogus = 999999;

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $bogus,
            'city_id' => $bogus,
            'address_line' => 'عنوان بمعرّفات من الشجرة الميتة',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['governorate_id', 'city_id']);
    }

    public function test_the_list_carries_names_so_the_app_need_not_refetch_the_pickers(): void
    {
        [$governorate, $city] = $this->place();
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'address_line' => 'شارع الأسماء',
        ])->assertCreated();

        $this->getJson('/api/v2/addresses')
            ->assertOk()
            ->assertJsonPath('data.0.city.name_ar', $city->name_ar)
            ->assertJsonPath('data.0.governorate.name_ar', $governorate->name_ar)
            ->assertJsonPath('data.0.country.name_en', 'Egypt');
    }

    public function test_the_address_relations_resolve_to_the_live_tables(): void
    {
        [$governorate, $city] = $this->place();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'address_line' => 'شارع العلاقات',
        ])->assertCreated();

        $address = $user->addresses()->first();

        // They pointed at Location, so ->city could only ever be null.
        $this->assertInstanceOf(City::class, $address->city);
        $this->assertInstanceOf(Governorate::class, $address->governorate);
        $this->assertInstanceOf(Country::class, $address->country);
        $this->assertSame($city->name_ar, $address->city->name_ar);
    }

    public function test_the_fee_engines_geo_context_now_matches_the_admin_picker(): void
    {
        // ServiceFeeRuleEngine reads addresses.governorate_id raw and compares it
        // to ids the fee-rule admin took from `governorates`. Before this, the
        // address held an id from a different table entirely — latent only
        // because no address could exist.
        [$governorate, $city] = $this->place();
        $business = $this->makeUser();
        $business->type = User::TYPE_BUSINESS;
        $business->save();

        Sanctum::actingAs($business);

        $this->postJson('/api/v2/addresses', [
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'address_line' => 'مقر النشاط',
        ])->assertCreated();

        $this->assertDatabaseHas('governorates', [
            'id' => $business->addresses()->first()->governorate_id,
        ]);
    }
}
