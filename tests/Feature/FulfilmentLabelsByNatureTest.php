<?php

namespace Tests\Feature;

use App\Models\BusinessMenuSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The fulfilment labels shown to a business (and its customers) follow what
 * it actually sells (`retail`), never which root it is filed under — the
 * owner's own rule replacing "التسليم والاستلام". A goods-selling business
 * (retail) reads "شحن / استلام أرض المصنع" with a دولي/محلي sub-choice; any
 * other business reads the plain "توصيل / استلام".
 */
class FulfilmentLabelsByNatureTest extends TestCase
{
    use DatabaseTransactions;

    private const ANTIQUES_RETAIL_CHILD = 21; // exhibitions/antiques — has retail

    private const MARKETING_CHILD = 177; // شركات/تسويق — booking + business_offers + menu only, no retail

    private function makeBusiness(int $rootId, int $childId): User
    {
        $u = new User();
        $u->name = 'Shop ' . Str::random(4);
        $u->email = 'shop-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->category_id = $rootId;
        $u->category_child_id = $childId;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_a_retail_business_gets_freight_labels(): void
    {
        $business = $this->makeBusiness(21, self::ANTIQUES_RETAIL_CHILD);

        $labels = BusinessMenuSetting::labelsFor($business);

        $this->assertTrue($labels['is_freight']);
        $this->assertSame('شحن', $labels['delivery_ar']);
        $this->assertSame('استلام أرض المصنع', $labels['pickup_ar']);
    }

    public function test_a_non_retail_business_gets_plain_labels(): void
    {
        $business = $this->makeBusiness(22, self::MARKETING_CHILD);

        $labels = BusinessMenuSetting::labelsFor($business);

        $this->assertFalse($labels['is_freight']);
        $this->assertSame('توصيل', $labels['delivery_ar']);
        $this->assertSame('استلام', $labels['pickup_ar']);
    }

    public function test_the_public_business_page_carries_the_labels_and_shipping_scope(): void
    {
        $business = $this->makeBusiness(21, self::ANTIQUES_RETAIL_CHILD);

        $fulfillment = $this->getJson('/api/v2/businesses/' . $business->id)
            ->assertOk()
            ->json('data.fulfillment');

        $this->assertTrue($fulfillment['labels']['is_freight']);
        $this->assertArrayHasKey('international_shipping', $fulfillment);
        $this->assertArrayHasKey('domestic_shipping', $fulfillment);
    }

    public function test_the_web_settings_page_renders_freight_labels(): void
    {
        $business = $this->makeBusiness(21, self::ANTIQUES_RETAIL_CHILD);

        $html = $this->actingAs($business)
            ->get(route('business.menu-settings.edit', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('شحن', $html);
        $this->assertStringContainsString('استلام أرض المصنع', $html);
        $this->assertStringContainsString('a2ShippingScope', $html);
    }

    public function test_the_web_settings_page_renders_plain_labels_for_a_service_business(): void
    {
        $business = $this->makeBusiness(22, self::MARKETING_CHILD);

        $html = $this->actingAs($business)
            ->get(route('business.menu-settings.edit', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('توصيل', $html);
        $this->assertStringNotContainsString('a2ShippingScope', $html);
    }

    public function test_the_owner_can_save_the_shipping_scope(): void
    {
        $business = $this->makeBusiness(21, self::ANTIQUES_RETAIL_CHILD);

        $this->actingAs($business)->put(route('business.menu-settings.update', [], false), [
            'supports_delivery' => '1',
            'supports_pickup' => '1',
            'supports_international_shipping' => '1',
        ])->assertRedirect();

        $row = BusinessMenuSetting::where('business_id', $business->id)->first();
        $this->assertTrue((bool) $row->supports_international_shipping);
        $this->assertFalse((bool) $row->supports_domestic_shipping);
    }
}
