<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Database\Seeders\CarRentalChildSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «تأجير سيارات»: the trade that rents a car for a day, a week or a month — kept apart from the showroom that sells one.
 * It builds on the period booking a flat already rents by, so the checks are about the trade's SHAPE: where it sits,
 * how it books, and that it speaks about cars for hire, not cars for sale.
 */
class CarRentalChildTest extends TestCase
{
    use DatabaseTransactions;

    private function child(): int
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', 'تأجير سيارات')->value('id');

        if ($id <= 0) {
            (new CarRentalChildSeeder)->run();
            $id = (int) DB::table('category_children_master')->where('name_ar', 'تأجير سيارات')->value('id');
        }

        return $id;
    }

    public function test_it_sits_under_the_cars_root_with_its_fixed_id(): void
    {
        $id = $this->child();

        $this->assertSame(CarRentalChildSeeder::CHILD_ID, $id);
        $this->assertTrue(DB::table('category_parent_child')
            ->where('child_id', $id)
            ->where('parent_id', DB::table('categories')->where('slug', 'cars')->value('id'))->exists());
    }

    public function test_it_books_by_the_period_and_holds_a_named_unit(): void
    {
        $id = $this->child();
        $serviceId = (int) DB::table('platform_services')->where('key', PlatformService::KEY_BOOKING)->value('id');
        $rootId = (int) DB::table('category_service_configs')->where('child_id', $id)->where('platform_service_id', $serviceId)->where('is_active', 1)->value('category_id');

        $config = app(ChildServiceWriter::class)->storedConfig($rootId, $id, $serviceId);

        $this->assertSame(['booking_stay'], $config['allowed_item_types'] ?? []);
        $this->assertTrue((bool) ($config['requires_bookable_item'] ?? false));
        $this->assertSame(['date_range'], $config['required_fields'] ?? []);
        $this->assertSame('stay', $config['booking_pattern'] ?? null);
    }

    public function test_it_names_the_kinds_it_rents_and_never_speaks_of_a_sale(): void
    {
        $id = $this->child();

        $groups = DB::table('category_child_option as c')
            ->join('options as o', 'o.id', '=', 'c.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('c.child_id', $id)
            ->pluck('g.name_ar')->unique()->values()->all();

        $this->assertContains('نوع المركبة', $groups, 'a rental company must be able to say what kind of car a unit is');
        $this->assertContains('ماركات السيارات', $groups);
        $this->assertNotContains('نوع التعامل', $groups, 'every unit is for rent: there is no sale/rent/swap axis');
        $this->assertNotContains('حالة المنتج', $groups, 'new/used is a sale word');
    }

    public function test_the_booking_pattern_map_lists_it_so_the_pattern_seeder_does_not_strip_it(): void
    {
        $map = require database_path('seeders/data/booking_patterns.php');
        $patterns = $map['children'] ?? $map;

        $this->assertSame(['stay'], $patterns[CarRentalChildSeeder::CHILD_ID] ?? null);
    }

    public function test_running_the_seeder_twice_changes_nothing(): void
    {
        $this->child();
        $before = [DB::table('category_child_option')->where('child_id', CarRentalChildSeeder::CHILD_ID)->count(), DB::table('category_children_master')->count()];

        (new CarRentalChildSeeder)->run();

        $this->assertSame($before, [DB::table('category_child_option')->where('child_id', CarRentalChildSeeder::CHILD_ID)->count(), DB::table('category_children_master')->count()]);
    }
}
