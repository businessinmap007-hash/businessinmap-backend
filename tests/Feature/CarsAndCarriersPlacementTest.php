<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner 2026-10-18: the limousine stays under «سيارات» (a car with a driver), the lorry goes to «شحن وتوصيل» (it carries
 * goods), and a furniture-lifting winch exists beside it. Rent-or-sale on a car is the deal-type filter, never a trade.
 */
class CarsAndCarriersPlacementTest extends TestCase
{
    use DatabaseTransactions;

    private function rootsOf(string $childName): array
    {
        return DB::table('category_parent_child as p')
            ->join('category_children_master as m', 'm.id', '=', 'p.child_id')
            ->join('categories as r', 'r.id', '=', 'p.parent_id')
            ->where('m.name_ar', $childName)->pluck('r.slug')->all();
    }

    public function test_the_limousine_is_under_cars_and_the_lorry_under_shipping(): void
    {
        $this->assertSame(['cars'], $this->rootsOf('خدمة ليموزين'));
        $this->assertSame(['shipping-delivery'], $this->rootsOf('سيارات نقل'));
    }

    public function test_there_is_no_car_rental_trade_renting_is_a_deal_type(): void
    {
        $this->assertSame(0, DB::table('category_children_master')->where('name_ar', 'تأجير سيارات')->count());

        $deal = DB::table('option_groups')->where('name_ar', 'نوع التعامل')->value('id');
        $names = DB::table('options')->where('group_id', $deal)->pluck('name_ar')->implode('|');

        $this->assertStringContainsString('إيجار', $names);
    }

    public function test_a_furniture_winch_is_booked_under_shipping_and_delivery(): void
    {
        $this->assertSame(['shipping-delivery'], $this->rootsOf('ونش رفع أثاث'));

        $id = (int) DB::table('category_children_master')->where('name_ar', 'ونش رفع أثاث')->value('id');
        $serviceId = (int) DB::table('platform_services')->where('key', PlatformService::KEY_BOOKING)->value('id');
        $rootId = (int) DB::table('categories')->where('slug', 'shipping-delivery')->value('id');

        $config = app(ChildServiceWriter::class)->storedConfig($rootId, $id, $serviceId);

        $this->assertSame(['booking_appointment'], $config['allowed_item_types'] ?? []);

        $lines = DB::table('category_child_option as c')->join('options as o', 'o.id', '=', 'c.option_id')
            ->where('c.child_id', $id)->pluck('o.name_ar')->all();

        $this->assertContains('ونش نقل عفش', $lines, 'the hoist must be able to name its trade');
        $this->assertNotContains('آثاث', $lines, 'it lifts furniture, it does not sell it');
    }
}
