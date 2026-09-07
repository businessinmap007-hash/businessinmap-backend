<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «باقات منيو بأسماء شخصية وخصم مجمّع (زي "وجبة العيلة") بدل تسعير صنف-بصنف
 * فقط» — a fixed-composition combo, priced either as a flat amount for the
 * whole bundle or as the live sum of its components' current prices minus a
 * discount. Cart-facing as its own offering kind (docs/product-changes-plan.md,
 * قسم ب — منيو ومطاعم).
 */
class MenuBundleJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Secret-password1';

    private User $business;
    private MenuItem $burger;
    private MenuItem $fries;
    private MenuItem $drink;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = new User();
        $this->business->name = 'مطعم الباقات';
        $this->business->email = 'bundles-' . uniqid() . '@example.test';
        $this->business->phone = '0100' . random_int(1000000, 9999999);
        $this->business->password = self::PASSWORD;
        $this->business->type = User::TYPE_BUSINESS;
        $this->business->api_token = Str::random(80);
        $this->business->save();

        $section = MenuSection::query()->create([
            'business_id' => $this->business->id,
            'name_ar' => 'وجبات',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->burger = MenuItem::query()->create([
            'business_id' => $this->business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'برجر',
            'base_price' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->fries = MenuItem::query()->create([
            'business_id' => $this->business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'بطاطس',
            'base_price' => 30,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->drink = MenuItem::query()->create([
            'business_id' => $this->business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'مشروب',
            'base_price' => 20,
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }

    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function registerCustomer(): string
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'عميل الباقات',
            'email' => 'bundles-customer-' . uniqid() . '@example.test',
            'phone' => '0155' . random_int(1000000, 9999999),
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms_accepted' => true,
        ])->assertCreated();

        return $response->json('token');
    }

    private function businessToken(): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $this->business->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }

    /** @return array<string,mixed> */
    private function createFixedBundle(): array
    {
        return $this->actingWithToken($this->businessToken())->postJson('/api/v2/business/menu/bundles', [
            'name_ar' => 'وجبة العيلة',
            'pricing_mode' => 'fixed',
            'fixed_price' => 120,
            'items' => [
                ['menu_item_id' => $this->burger->id, 'qty' => 1],
                ['menu_item_id' => $this->fries->id, 'qty' => 1],
                ['menu_item_id' => $this->drink->id, 'qty' => 1],
            ],
        ])->assertCreated()->json('data');
    }

    public function test_the_owner_creates_a_flat_priced_bundle(): void
    {
        $bundle = $this->createFixedBundle();

        $this->assertSame('وجبة العيلة', $bundle['name_ar']);
        $this->assertEquals(120, $bundle['price']);
        // 100 + 30 + 20 — informational only, never charged for a fixed bundle.
        $this->assertEquals(150, $bundle['components_subtotal']);
        $this->assertCount(3, $bundle['items']);
    }

    public function test_a_discount_percent_bundle_prices_off_live_component_prices(): void
    {
        $this->actingWithToken($this->businessToken())->postJson('/api/v2/business/menu/bundles', [
            'name_ar' => 'كومبو الخصم',
            'pricing_mode' => 'discount_percent',
            'discount_value' => 10,
            'items' => [
                ['menu_item_id' => $this->burger->id, 'qty' => 1],
                ['menu_item_id' => $this->fries->id, 'qty' => 2],
            ],
        ])->assertCreated()
            // (100 + 30*2) * 0.9 = 144
            ->assertJsonPath('data.components_subtotal', 160)
            ->assertJsonPath('data.price', 144);
    }

    public function test_a_bundle_needs_at_least_two_components(): void
    {
        $this->actingWithToken($this->businessToken())->postJson('/api/v2/business/menu/bundles', [
            'name_ar' => 'باقة ناقصة',
            'pricing_mode' => 'fixed',
            'fixed_price' => 50,
            'items' => [
                ['menu_item_id' => $this->burger->id, 'qty' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_the_customer_facing_menu_lists_the_bundle_with_its_contents(): void
    {
        $this->createFixedBundle();

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)->assertOk()->json('data');

        $bundleSection = collect($menu['sections'])->firstWhere('source', 'bundle');
        $this->assertNotNull($bundleSection);
        $bundleItem = $bundleSection['items'][0];

        $this->assertSame('bundle', $bundleItem['kind']);
        $this->assertEquals(120, $bundleItem['base_price']);
        $this->assertStringContainsString('برجر', $bundleItem['description']);
        $this->assertStringContainsString('بطاطس', $bundleItem['description']);
        $this->assertStringContainsString('مشروب', $bundleItem['description']);
    }

    public function test_a_customer_adds_the_bundle_to_the_cart_at_the_bundle_price(): void
    {
        $bundle = $this->createFixedBundle();
        $token = $this->registerCustomer();

        $this->actingWithToken($token)->postJson('/api/v2/cart/items', [
            'kind' => 'bundle',
            'offering_id' => $bundle['id'],
            'qty' => 2,
        ])->assertSuccessful();

        $cart = $this->actingWithToken($token)->getJson('/api/v2/cart')->assertOk()->json('data');
        $carts = $cart['carts'] ?? [$cart];
        $line = collect($carts[0]['items'])->first();

        $this->assertSame('bundle', $line['kind']);
        $this->assertSame('وجبة العيلة', $line['name']);
        $this->assertEquals(120, $line['price']);
        $this->assertEquals(240, $line['total_price']);
        // The fixed component list rides along as the line's sub-text, same
        // rendering path as a menu item's extras.
        $this->assertEqualsCanonicalizing(
            ['برجر', 'بطاطس', 'مشروب'],
            $line['options']['extras']
        );
    }

    public function test_a_stranger_cannot_manage_another_businesss_bundle(): void
    {
        $bundle = $this->createFixedBundle();

        $stranger = new User();
        $stranger->name = 'صاحب محل آخر';
        $stranger->email = 'bundle-stranger-' . uniqid() . '@example.test';
        $stranger->phone = '0111' . random_int(1000000, 9999999);
        $stranger->password = self::PASSWORD;
        $stranger->type = User::TYPE_BUSINESS;
        $stranger->api_token = Str::random(80);
        $stranger->save();

        $strangerToken = $this->postJson('/api/v2/auth/login', [
            'email' => $stranger->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');

        $this->actingWithToken($strangerToken)
            ->putJson('/api/v2/business/menu/bundles/' . $bundle['id'], [
                'name_ar' => 'تسلل',
                'pricing_mode' => 'fixed',
                'fixed_price' => 1,
                'items' => [
                    ['menu_item_id' => $this->burger->id, 'qty' => 1],
                    ['menu_item_id' => $this->fries->id, 'qty' => 1],
                ],
            ])->assertNotFound();
    }
}
