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
 * The "I trust" checkbox between the parties of an order (OrderTrustController).
 */
class OrderTrustTest extends TestCase
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

    private function checkout(User $business, User $customer, string $fulfillmentType = 'delivery'): int
    {
        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $itemId = $menu['sections'][0]['items'][0]['id'];

        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $itemId, 'qty' => 1,
        ])->assertSuccessful();

        $payload = ['fulfillment_type' => $fulfillmentType];
        $payload = $fulfillmentType === 'delivery'
            ? $payload + ['address' => 'شارع الاختبار']
            : $payload + ['pickup_at' => now()->addHour()->toIso8601String()];

        $order = $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', $payload)
            ->assertCreated()->json('data.order');

        return (int) $order['id'];
    }

    /** @return array{order_id: int, driver_token: string} */
    private function deliveredOrder(User $business, User $customer): array
    {
        $orderId = $this->checkout($business, $customer, 'delivery');
        $businessToken = $this->tokenFor($business);
        $customerToken = $this->tokenFor($customer);

        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $driverToken = $this->tokenFor($driver);
        $this->carrierActing($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $pickupToken = $this->actingWithToken($businessToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')->assertOk()->json('data.pickup_token');
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')->assertOk();

        $deliveryToken = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')->assertOk()->json('data.delivery_token');
        $this->actingWithToken($customerToken)->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')->assertOk();

        return ['order_id' => $orderId, 'driver_token' => $driverToken];
    }

    public function test_a_driver_can_trust_the_customer_and_it_shows_on_both_sides(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestT1');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustT1');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->deliveredOrder($business, $customer);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/orders/' . $orderId . '/trust', ['party' => 'customer', 'trusted' => true])
            ->assertOk()->assertJsonPath('data.trusted', true);

        $detail = $this->actingWithToken($this->tokenFor($customer))->getJson('/api/v2/orders/' . $orderId)->assertOk();
        $this->assertTrue($detail->json('data.trust.driver.trusts_me'));
        $this->assertFalse($detail->json('data.trust.driver.trusted_by_me'));

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/orders/' . $orderId . '/trust', ['party' => 'customer', 'trusted' => false])
            ->assertOk()->assertJsonPath('data.trusted', false);
    }

    public function test_the_merchant_can_trust_the_driver(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestT2');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustT2');
        ['order_id' => $orderId] = $this->deliveredOrder($business, $customer);

        $this->actingWithToken($this->tokenFor($business))
            ->postJson('/api/v2/business/orders/' . $orderId . '/trust', ['party' => 'driver', 'trusted' => true])
            ->assertOk()->assertJsonPath('data.trusted', true);
    }

    public function test_the_merchant_cannot_trust_a_customer_without_an_active_guarantee(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestT3');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustT3');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $this->actingWithToken($this->tokenFor($business))
            ->postJson('/api/v2/business/orders/' . $orderId . '/trust', ['party' => 'customer', 'trusted' => true])
            ->assertStatus(422);
        $this->assertFalse(\App\Models\PartyTrust::exists($business->id, $customer->id));
    }

    public function test_a_stranger_cannot_tick_trust_on_someone_elses_order(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestT4');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustT4');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $stranger = $this->makeUser(User::TYPE_CLIENT, 'StrangerT4');
        $this->actingWithToken($this->tokenFor($stranger))
            ->postJson('/api/v2/orders/' . $orderId . '/trust', ['party' => 'business', 'trusted' => true])
            ->assertStatus(404);
    }

    public function test_a_party_cannot_trust_themselves(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestT5');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustT5');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $this->actingWithToken($this->tokenFor($customer))
            ->postJson('/api/v2/orders/' . $orderId . '/trust', ['party' => 'customer', 'trusted' => true])
            ->assertStatus(422);
    }
}
