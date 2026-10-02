<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نفذ الحل المقترح للسيارات واى تصنيف اخر» — المالك، 2026-10-02: what a
 * merchant states for ONE unit (a car's year, mileage, gearbox, colour), kept
 * apart from its catalog master, shown on the product page and searched across
 * shops to compare prices. Rolls back.
 */
class MenuItemAttributesTest extends TestCase
{
    use DatabaseTransactions;

    private function carShop(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', 188)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #188 (معرض سيارات).');
    }

    /**
     * The cars kind as the owner set it — written here, not read from live
     * data, so an admin re-tuning «أشكال المنيو» cannot fail this file.
     */
    private function carsKind(): OptionGroup
    {
        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();
        $group = OptionGroup::query()->where('name_ar', 'نوع المركبة')->firstOrFail();
        $group->update(['menu_detail_profile_id' => $cars->id]);

        $onCard = ['model_year', 'mileage_km', 'transmission', 'fuel_type'];
        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $cars->id)->delete();
        foreach (['model_year', 'mileage_km', 'transmission', 'fuel_type', 'engine_cc', 'color'] as $i => $code) {
            DB::table('menu_detail_profile_attributes')->insert([
                'menu_detail_profile_id' => $cars->id, 'catalog_attribute_id' => $this->attr($code),
                'sort_order' => ($i + 1) * 10, 'show_on_card' => in_array($code, $onCard, true), 'per_item' => true,
                'is_filterable' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $group;
    }

    /** @return array{0:int,1:int} [line option id, catalog product id] */
    private function sedanAndCar(User $shop): array
    {
        $group = $this->carsKind();
        $sedan = (int) DB::table('options')->where('group_id', $group->id)->where('name_ar', 'سيدان')->value('id');
        // A shop only prices what it has ticked (option_user) — tick it for the test.
        DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $sedan], []);

        $car = (int) DB::table('catalog_products')->where('product_category_child_id', 76)->whereNull('deleted_at')->orderBy('id')->value('id');
        $this->assertGreaterThan(0, $car, 'a seeded car master exists');

        return [$sedan, $car];
    }

    private function attr(string $code): int
    {
        return (int) DB::table('catalog_attributes')->where('code', $code)->value('id');
    }

    private function option(string $attribute, string $slug): int
    {
        return (int) DB::table('catalog_attribute_options')->where('attribute_id', $this->attr($attribute))->where('slug', $slug)->value('id');
    }

    private function postCar(User $shop, array $extra = [], int $price = 400000)
    {
        [$sedan, $car] = $this->sedanAndCar($shop);
        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'كورولا للبيع',
            'base_price' => $price,
            'catalog_product_id' => $car,
            'line_option_id' => $sedan,
        ] + $extra);
    }

    public function test_the_cars_kind_asks_the_merchant_for_year_mileage_gearbox_fuel_and_colour_per_unit(): void
    {
        $shop = $this->carShop();
        $this->sedanAndCar($shop);
        Sanctum::actingAs($shop);

        $group = OptionGroup::query()->where('name_ar', 'نوع المركبة')->firstOrFail();
        $line = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines'))->firstWhere('group_id', $group->id);

        $this->assertNotNull($line, 'the car type group is in a showroom\'s vocabulary');
        $fields = collect($line['detail_profile']['fields'])->keyBy('code');
        foreach (['model_year', 'mileage_km', 'transmission', 'fuel_type', 'color'] as $code) {
            $this->assertTrue($fields[$code]['per_item'], "{$code} is stated for each unit");
        }
        $this->assertSame(['أوتوماتيك', 'مانيوال'], array_column($fields['transmission']['options'], 'name'), 'gearbox is a choice, not typed');
    }

    public function test_a_unit_keeps_its_own_values_and_the_public_menu_lists_them_beside_the_masters(): void
    {
        $shop = $this->carShop();
        $res = $this->postCar($shop, ['attributes' => [
            $this->attr('model_year') => '2021',
            $this->attr('mileage_km') => '42,500',
            $this->attr('transmission') => $this->option('transmission', 'automatic'),
            $this->attr('color') => $this->option('color', 'white'),
        ]])->assertCreated();

        $own = collect($res->json('data.attributes'))->keyBy('code');
        $this->assertEquals(2021, $own['model_year']['number']);
        $this->assertEquals(42500, $own['mileage_km']['number'], 'thousands separators are tolerated');
        $this->assertSame('أوتوماتيك', $own['transmission']['value']);

        $public = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu/' . $shop->id)->assertOk()->json('data.sections');
        $item = collect($public)->flatMap(fn ($s) => $s['items'])->firstWhere('id', $res->json('data.id'));
        $specs = collect($item['specs'])->keyBy('code');

        $this->assertSame('42,500', number_format((float) preg_replace('/[^0-9.]/', '', $specs['mileage_km']['value'])));
        $this->assertArrayHasKey('brand', $specs, 'the master\'s own spec is still there');
        $this->assertSame('أبيض', $specs['color']['value']);
        $this->assertSame('2021 · 42500 كم · أوتوماتيك', $item['card_summary'], 'the card line follows the on-card fields of the kind, in order');
    }

    public function test_a_value_that_does_not_fit_its_field_is_refused_and_nothing_is_saved(): void
    {
        $shop = $this->carShop();
        $before = DB::table('menu_items')->where('business_id', $shop->id)->count();

        $this->postCar($shop, ['attributes' => [$this->attr('model_year') => 'حديثة']])
            ->assertStatus(422)->assertJsonValidationErrors('attributes.' . $this->attr('model_year'));

        // A gearbox option that belongs to the FUEL attribute.
        $this->postCar($shop, ['attributes' => [$this->attr('transmission') => $this->option('fuel_type', 'diesel')]])
            ->assertStatus(422);

        $this->assertSame($before, DB::table('menu_items')->where('business_id', $shop->id)->count(), 'the item is not left half-saved');
    }

    public function test_a_field_the_kind_does_not_ask_is_ignored_not_stored(): void
    {
        $shop = $this->carShop();
        $res = $this->postCar($shop, ['attributes' => [$this->attr('battery_mah') => '4000']])->assertCreated();

        $this->assertSame([], $res->json('data.attributes'), 'a car has no battery field');
    }

    public function test_editing_the_price_alone_never_wipes_the_units_values_but_a_blank_removes_one(): void
    {
        $shop = $this->carShop();
        $year = $this->attr('model_year');
        $km = $this->attr('mileage_km');
        $id = $this->postCar($shop, ['attributes' => [$year => '2019', $km => '90000']])->assertCreated()->json('data.id');
        [$sedan, $car] = $this->sedanAndCar($shop);

        $body = ['name_ar' => 'كورولا للبيع', 'base_price' => 380000, 'catalog_product_id' => $car, 'line_option_id' => $sedan];

        $this->putJson("/api/v2/business/menu/items/{$id}", $body)->assertOk();
        $this->assertCount(2, DB::table('menu_item_attribute_values')->where('menu_item_id', $id)->get(), 'no `attributes` key = untouched');

        $this->putJson("/api/v2/business/menu/items/{$id}", $body + ['attributes' => [$km => '']])->assertOk();
        $left = DB::table('menu_item_attribute_values')->where('menu_item_id', $id)->pluck('attribute_id')->all();
        $this->assertSame([$year], $left, 'a blank value removes just that one');
    }

    public function test_search_filters_by_the_units_values_and_compares_one_product_across_shops_cheapest_first(): void
    {
        $shopA = $this->carShop();
        $shopB = User::query()->where('type', 'business')->where('category_child_id', 188)->where('id', '!=', $shopA->id)->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a second car showroom.');

        $year = $this->attr('model_year');
        $gear = $this->attr('transmission');
        $auto = $this->option('transmission', 'automatic');
        $manual = $this->option('transmission', 'manual');
        [, $car] = $this->sedanAndCar($shopA);

        $a = $this->postCar($shopA, ['attributes' => [$year => 2022, $gear => $auto]], 520000)->assertCreated()->json('data.id');
        $this->sedanAndCar($shopB);
        $b = $this->postCar($shopB, ['attributes' => [$year => 2018, $gear => $manual]], 300000)->assertCreated()->json('data.id');

        $search = fn (array $q) => $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu-items/search?' . http_build_query($q + ['profile' => 'cars', 'per_page' => 50]));

        // Compare one product: every shop that has it, cheapest first.
        $ids = collect($search(['catalog_product_id' => $car])->assertOk()->json('data.items'))->pluck('id')->all();
        $this->assertSame([$b, $a], array_slice(array_values(array_intersect($ids, [$a, $b])), 0, 2), 'cheapest first');

        // A range on the unit's own year.
        $ids = collect($search(['attr' => ['model_year_min' => 2020]])->json('data.items'))->pluck('id')->all();
        $this->assertContains($a, $ids);
        $this->assertNotContains($b, $ids, '2018 is outside 2020+');

        // A choice on the gearbox.
        $ids = collect($search(['attr' => ['transmission' => $manual]])->json('data.items'))->pluck('id')->all();
        $this->assertContains($b, $ids);
        $this->assertNotContains($a, $ids);

        // The facets say what is on offer.
        $facets = $search([])->json('data.facets');
        $this->assertSame('number', $facets['model_year']['type']);
        $this->assertLessThanOrEqual(2018, $facets['model_year']['min']);
        $this->assertGreaterThanOrEqual(2022, $facets['model_year']['max']);
        $this->assertSame(['أوتوماتيك', 'مانيوال'], collect($facets['transmission']['options'])->pluck('name')->sort()->values()->all());

        // Each result carries the kind's one-line summary, and paging rides inside data.
        $first = collect($search(['catalog_product_id' => $car])->json('data.items'))->firstWhere('id', $a);
        $this->assertSame('2022 · أوتوماتيك', $first['summary']);
        $this->assertGreaterThanOrEqual(2, $search([])->json('data.meta.total'));

        // The kinds a customer can search list only their FILTER fields, and only kinds on sale.
        DB::table('menu_detail_profile_attributes')
            ->where('menu_detail_profile_id', MenuDetailProfile::query()->where('code', 'cars')->value('id'))
            ->where('catalog_attribute_id', $this->attr('color'))->update(['is_filterable' => 0]);
        $kinds = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu-items/kinds')->assertOk()->json('data.kinds'));
        $cars = $kinds->firstWhere('code', 'cars');
        $this->assertNotNull($cars, 'cars are on sale');
        $this->assertNotContains('color', array_column($cars['fields'], 'code'), 'an unticked filter is not offered');
        $this->assertContains('model_year', array_column($cars['fields'], 'code'));
        $this->assertNull($kinds->firstWhere('code', 'appliances'), 'a kind nobody sells is not listed');

        $this->getJson('/api/v2/discovery/menu-items/search?profile=nope')->assertStatus(404);
    }

    public function test_the_admin_marks_which_fields_the_merchant_states_per_unit(): void
    {
        $admin = User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();
        $year = $this->attr('model_year');
        $color = $this->attr('color');

        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $cars, false), [
            'fields' => [
                $year => ['enabled' => 1, 'sort_order' => 10, 'per_item' => 1],
                $color => ['enabled' => 1, 'sort_order' => 20],
            ],
        ])->assertRedirect();

        $fields = collect(MenuDetailProfile::fieldsFor([$cars->id])[$cars->id])->keyBy('code');
        $this->assertTrue($fields['model_year']['per_item']);
        $this->assertFalse($fields['color']['per_item'], 'unticked = taken from the catalog');
    }
}
