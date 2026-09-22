<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Concerns\PromotesCarriers;
use Tests\TestCase;

/**
 * A business's own flat delivery charge (Api\V2\DeliveryController::
 * updateDeliverySettings), applied at checkout, and a freelance driver's
 * own rate as a fallback only when the business never set one
 * (DeliveryDispatchService::acceptOrder).
 */
class DeliveryFeeTest extends TestCase
{
    use PromotesCarriers;
    use DatabaseTransactions;

    private const RESTAURANT_CHILD = 245;

    private const RESTAURANT_ROOT = 16;

    private function makeUser(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;

        if ($type === User::TYPE_BUSINESS) {
            $u->category_id = self::RESTAURANT_ROOT;
            $u->category_child_id = self::RESTAURANT_CHILD;
        }

        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk()->json('token');
    }

    private function seedMenu(User $business): void
    {
        $section = MenuSection::query()->create([
            'business_id' => $business->id, 'name_ar' => 'الأطباق', 'is_active' => true, 'sort_order' => 1,
        ]);
        MenuItem::query()->create([
            'business_id' => $business->id, 'menu_section_id' => $section->id,
            'name_ar' => 'كشري', 'base_price' => 45, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    /** @return array{order_id: int, total_before_delivery: float} */
    private function checkoutDelivery(User $business, User $customer): array
    {
        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $itemId = $menu['sections'][0]['items'][0]['id'];

        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $itemId, 'qty' => 1,
        ])->assertSuccessful();

        $order = $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', [
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('governorates')->orderBy('id')->value('id'),
            'fulfillment_type' => 'delivery',
            'address' => 'شارع الاختبار',
        ])->assertCreated()->json('data.order');

        return ['order_id' => (int) $order['id']];
    }

    public function test_the_owner_can_read_and_set_their_delivery_fee(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $token = $this->tokenFor($owner);

        $this->actingWithToken($token)
            ->getJson('/api/v2/business/delivery-settings')
            ->assertOk()
            ->assertJsonPath('data.delivery_fee_amount', null);

        $this->actingWithToken($token)
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])
            ->assertOk()
            ->assertJsonPath('data.delivery_fee_amount', 30);

        $this->assertSame(30.0, (float) $owner->fresh()->delivery_fee_amount);
    }

    public function test_checkout_adds_the_businesss_configured_delivery_fee(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest2');
        $this->seedMenu($owner);
        $this->actingWithToken($this->tokenFor($owner))
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])
            ->assertOk();

        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust2');
        $result = $this->checkoutDelivery($owner, $customer);

        $order = Order::find($result['order_id']);
        $this->assertSame(30.0, (float) $order->delivery_fee);
        // The pre-existing billing formula (menu + service_fee + tax -
        // discount) is untouched - only confirm delivery_fee flowed into it.
        $this->assertGreaterThanOrEqual(30.0 + 45.0 - 0.01, (float) $order->final_total);
    }

    public function test_checkout_leaves_delivery_free_when_the_business_never_set_a_fee(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest3');
        $this->seedMenu($owner);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust3');

        $result = $this->checkoutDelivery($owner, $customer);

        $order = Order::find($result['order_id']);
        $this->assertSame(0.0, (float) $order->delivery_fee);
    }

    public function test_pickup_checkout_ignores_the_businesss_delivery_fee(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest4');
        $this->seedMenu($owner);
        $this->actingWithToken($this->tokenFor($owner))
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])
            ->assertOk();

        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust4');
        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $owner->id)->assertOk()->json('data');
        $itemId = $menu['sections'][0]['items'][0]['id'];

        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $itemId, 'qty' => 1,
        ])->assertSuccessful();

        $order = $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $owner->id . '/checkout', [
            'fulfillment_type' => 'pickup',
            'pickup_at' => now()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.order');

        $this->assertSame(0.0, (float) Order::find($order['id'])->delivery_fee);
    }

    public function test_the_business_page_exposes_the_delivery_fee_and_a_delivery_only_price_row_earns_no_services_tab(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'RestPage');
        $this->seedMenu($owner);
        $this->actingWithToken($this->tokenFor($owner))
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])->assertOk();
        \App\Models\BusinessServicePrice::create([
            'business_id' => $owner->id, 'service_id' => (int) \DB::table('platform_services')->where('key', 'delivery')->value('id'),
            'child_id' => (int) $owner->category_child_id, 'bookable_item_type' => 'delivery',
            'price' => 30, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $page = $this->getJson('/api/v2/businesses/' . $owner->id)->assertOk();
        $this->assertSame(30.0, (float) $page->json('data.fulfillment.delivery_fee'));
        $this->assertFalse((bool) $page->json('data.sections.services'));
    }

    public function test_a_freelance_driver_can_set_their_own_delivery_fee(): void
    {
        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $token = $this->tokenFor($driver);

        $this->carrierActing($token)->postJson('/api/v2/delivery/register')->assertCreated();

        $this->actingWithToken($token)
            ->patchJson('/api/v2/delivery/delivery-fee', ['delivery_fee_amount' => 20])
            ->assertOk()
            ->assertJsonPath('data.delivery_fee_amount', 20);

        $this->assertSame(20.0, (float) DeliveryDriver::where('user_id', $driver->id)->value('delivery_fee_amount'));
    }

    public function test_a_freelance_drivers_fee_applies_only_when_the_business_never_set_one(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest5');
        $this->seedMenu($owner);

        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust5');
        $result = $this->checkoutDelivery($owner, $customer);

        $this->actingWithToken($this->tokenFor($owner))->postJson('/api/v2/business/orders/' . $result['order_id'] . '/accept')->assertSuccessful();
        $this->actingWithToken($this->tokenFor($owner))->postJson('/api/v2/business/orders/' . $result['order_id'] . '/preparing')->assertSuccessful();

        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider5');
        $driverToken = $this->tokenFor($driver);
        $this->carrierActing($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->patchJson('/api/v2/delivery/delivery-fee', ['delivery_fee_amount' => 20])->assertOk();

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $result['order_id'] . '/accept')->assertCreated();

        $order = Order::find($result['order_id']);
        $this->assertSame(20.0, (float) $order->delivery_fee);
        $this->assertGreaterThanOrEqual(20.0 + 45.0 - 0.01, (float) $order->final_total);
    }

    public function test_a_freelance_drivers_fee_never_overrides_the_businesss_own_fee(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest6');
        $this->seedMenu($owner);
        $this->actingWithToken($this->tokenFor($owner))
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])
            ->assertOk();

        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust6');
        $result = $this->checkoutDelivery($owner, $customer);

        $this->actingWithToken($this->tokenFor($owner))->postJson('/api/v2/business/orders/' . $result['order_id'] . '/accept')->assertSuccessful();
        $this->actingWithToken($this->tokenFor($owner))->postJson('/api/v2/business/orders/' . $result['order_id'] . '/preparing')->assertSuccessful();

        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider6');
        $driverToken = $this->tokenFor($driver);
        $this->carrierActing($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->patchJson('/api/v2/delivery/delivery-fee', ['delivery_fee_amount' => 20])->assertOk();

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $result['order_id'] . '/accept')->assertCreated();

        // The business's own fee (30) already charged at checkout must win
        // over the freelance driver's own rate (20).
        $order = Order::find($result['order_id']);
        $this->assertSame(30.0, (float) $order->delivery_fee);
    }
}
