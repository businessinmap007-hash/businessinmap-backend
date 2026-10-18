<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** «سيارة من المالك» can be visited: a buyer books a time to see the car and try it (rent-or-sale is the deal-type filter). */
class CarOwnerViewingTest extends TestCase
{
    use DatabaseTransactions;

    /** The private seller can be visited: a unit-free appointment, not a held car. */
    public function test_a_private_seller_takes_a_viewing_appointment(): void
    {
        $owner = (int) DB::table('category_children_master')->where('name_ar', 'سيارة من المالك')->value('id');
        $serviceId = (int) DB::table('platform_services')->where('key', PlatformService::KEY_BOOKING)->value('id');
        $rootId = (int) DB::table('category_service_configs')->where('child_id', $owner)->where('platform_service_id', $serviceId)->where('is_active', 1)->value('category_id');

        $this->assertGreaterThan(0, $rootId, 'a private seller has no live booking config');

        $config = app(ChildServiceWriter::class)->storedConfig($rootId, $owner, $serviceId);

        $this->assertSame(['booking_appointment'], $config['allowed_item_types'] ?? []);
        $this->assertFalse((bool) ($config['requires_bookable_item'] ?? true));
    }
}
