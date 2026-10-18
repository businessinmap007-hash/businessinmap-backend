<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\User;
use Database\Seeders\CarRentalSpecsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner 2026-10-18: a car can be for sale or for rent and «نوع التعامل» is the filter — «إذا اختار للبيع تكون مواصفات،
 * وإذا كانت إيجار لها المواصفات الخاصة به». One kind of listing; the fields follow the tick.
 */
class CarDealTypeSpecsTest extends TestCase
{
    use \Tests\Concerns\AnswersDelivery;
    use DatabaseTransactions;

    private int $rent;

    protected function setUp(): void
    {
        parent::setUp();

        (new CarRentalSpecsSeeder)->run();
        $this->rent = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نوع التعامل')->where('o.name_ar', 'إيجار')->value('o.id');
    }

    private function attr(string $code): int
    {
        return (int) DB::table('catalog_attributes')->where('code', $code)->value('id');
    }

    private function option(string $attribute, string $slug): int
    {
        return (int) DB::table('catalog_attribute_options')->where('attribute_id', $this->attr($attribute))->where('slug', $slug)->value('id');
    }

    private function shop(): User
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 188)->orderBy('id')->first();
        $shop ?: $this->markTestSkipped('No business stands on child #188 (معرض سيارات).');

        $group = OptionGroup::query()->where('name_ar', 'نوع المركبة')->firstOrFail();
        $group->update(['menu_detail_profile_id' => MenuDetailProfile::query()->where('code', 'cars')->value('id')]);

        foreach ([(int) DB::table('options')->where('group_id', $group->id)->where('name_ar', 'سيدان')->value('id'), $this->rent] as $optionId) {
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $optionId], []);
        }

        return $shop;
    }

    private function postCar(User $shop, array $extra)
    {
        $sedan = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نوع المركبة')->where('o.name_ar', 'سيدان')->value('o.id');
        $car = (int) DB::table('catalog_products')->where('product_category_child_id', 76)->whereNull('deleted_at')->orderBy('id')->value('id');
        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'كورولا', 'base_price' => 900, 'catalog_product_id' => $car, 'line_option_id' => $sedan,
        ] + $extra);
    }

    private function storedCodes(int $itemId): array
    {
        return DB::table('menu_item_attribute_values as v')->join('catalog_attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('v.menu_item_id', $itemId)->pluck('a.code')->all();
    }

    public function test_the_cars_kind_hangs_rental_terms_on_the_rent_tick_and_takes_the_mileage_off_it(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);

        $group = OptionGroup::query()->where('name_ar', 'نوع المركبة')->firstOrFail();
        $line = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines'))->firstWhere('group_id', $group->id);
        $fields = collect($line['detail_profile']['fields'])->keyBy('code');

        foreach (['rental_driver', 'rental_insurance', 'km_per_day', 'min_renter_age', 'rental_deposit', 'weekly_rate', 'monthly_rate'] as $code) {
            $this->assertSame($this->rent, $fields[$code]['only_with_option_id'], "{$code} is a rental term");
        }

        $this->assertSame($this->rent, $fields['mileage_km']['not_with_option_id'], 'mileage is a sale figure');
        $this->assertNull($fields['model_year']['only_with_option_id'], 'the year is asked either way');
        $this->assertSame(['بدون سائق', 'بسائق', 'بسائق أو بدونه'], array_column($fields['rental_driver']['options'], 'name'));
    }

    public function test_a_rental_keeps_its_terms_and_drops_the_mileage(): void
    {
        $shop = $this->shop();

        $id = $this->postCar($shop, [
            'modifier_option_ids' => [$this->rent],
            'attributes' => [
                $this->attr('model_year') => '2022',
                $this->attr('mileage_km') => '30000',
                $this->attr('rental_driver') => $this->option('rental_driver', 'with-driver'),
                $this->attr('km_per_day') => '200',
                $this->attr('weekly_rate') => '5000',
            ],
        ])->assertCreated()->json('data.id');

        $stored = $this->storedCodes($id);

        $this->assertEqualsCanonicalizing(['model_year', 'rental_driver', 'km_per_day', 'weekly_rate'], $stored);
    }

    public function test_a_sale_keeps_the_mileage_and_ignores_rental_terms(): void
    {
        $shop = $this->shop();

        $id = $this->postCar($shop, [
            'attributes' => [
                $this->attr('model_year') => '2019',
                $this->attr('mileage_km') => '90000',
                $this->attr('rental_driver') => $this->option('rental_driver', 'self-drive'),
                $this->attr('weekly_rate') => '5000',
            ],
        ])->assertCreated()->json('data.id');

        $this->assertEqualsCanonicalizing(['model_year', 'mileage_km'], $this->storedCodes($id));
    }

    public function test_turning_a_sale_into_a_rental_removes_the_figure_that_no_longer_applies(): void
    {
        $shop = $this->shop();
        $year = $this->attr('model_year');
        $km = $this->attr('mileage_km');

        $id = $this->postCar($shop, ['attributes' => [$year => '2019', $km => '90000']])->assertCreated()->json('data.id');
        $this->assertContains('mileage_km', $this->storedCodes($id));

        $sedan = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نوع المركبة')->where('o.name_ar', 'سيدان')->value('o.id');
        $car = (int) DB::table('catalog_products')->where('product_category_child_id', 76)->whereNull('deleted_at')->orderBy('id')->value('id');

        $this->putJson("/api/v2/business/menu/items/{$id}", [
            'name_ar' => 'كورولا', 'base_price' => 900, 'catalog_product_id' => $car, 'line_option_id' => $sedan,
            'modifier_option_ids' => [$this->rent],
            'attributes' => [$year => '2019', $this->attr('rental_insurance') => $this->option('rental_insurance', 'comprehensive')],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(['model_year', 'rental_insurance'], $this->storedCodes($id));
    }

    public function test_the_seeder_is_idempotent_and_the_admin_rebuild_keeps_the_dependencies(): void
    {
        $cars = (int) DB::table('menu_detail_profiles')->where('code', 'cars')->value('id');
        $before = DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $cars)->count();

        (new CarRentalSpecsSeeder)->run();

        $this->assertSame($before, DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $cars)->count());
        $this->assertSame(7, DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $cars)->where('only_with_option_id', $this->rent)->count());
    }

    /** «سيدان — كيا»: the make describes the car; it is neither a second shelf nor a price axis. */
    public function test_the_make_describes_the_car_instead_of_being_a_second_section_or_a_price_axis(): void
    {
        foreach ([188, 549] as $child) {
            $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first();

            if (! $shop) {
                continue;
            }

            Sanctum::actingAs($shop);
            $vocab = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');

            $this->assertSame(['نوع المركبة'], array_column($vocab['lines'], 'group_name'), "child {$child}: only the kind of car is a section");

            $brand = collect($vocab['modifiers'])->firstWhere('group_name', 'ماركات السيارات');
            $this->assertTrue($brand['descriptive'], "child {$child}: the make describes the car");
            $this->assertFalse($brand['multiple'], 'a car has one make');
            $this->assertSame('dropdown', $brand['display'], '43 makes: a dropdown, not a wall of buttons');
            $this->assertNotContains('ماركات السيارات', array_column($vocab['price_axes'], 'group_name'), 'no price per make');
        }
    }
}
