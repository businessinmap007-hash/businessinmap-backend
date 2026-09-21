<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Tests\TestCase;

class ServiceNamesAndChipsTest extends TestCase
{
    public function test_the_two_services_have_their_new_names(): void
    {
        $this->assertSame('منيو', PlatformService::query()->where('key', 'menu')->value('name_ar'));
        $this->assertSame('كاتلوج', PlatformService::query()->where('key', 'retail')->value('name_ar'));
    }

    public function test_the_service_type_chips_do_not_offer_delivery(): void
    {
        $keys = collect($this->getJson('/api/v2/discovery/service-types')->assertOk()->json('data.services'))->pluck('key')->all();

        $this->assertNotContains('delivery', $keys);
        $this->assertContains('menu', $keys);
        $this->assertContains('retail', $keys);
    }
}
