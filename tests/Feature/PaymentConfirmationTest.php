<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Three independent cash-payment attestations for the no-wallet COD flow
 * (OrderController::confirmPayment/businessConfirmPayment,
 * DeliveryDispatchService::confirmPaymentReceived) - the customer confirms
 * they paid, the merchant confirms they received the order amount, and the
 * driver confirms they received the delivery fee. Never a chain: each is
 * its own single-actor, one-shot attestation.
 */
class PaymentConfirmationTest extends TestCase
{
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
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $pickupToken = $this->actingWithToken($businessToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')->assertOk()->json('data.pickup_token');
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')->assertOk();

        $deliveryToken = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')->assertOk()->json('data.delivery_token');
        $this->actingWithToken($customerToken)->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')->assertOk();

        return ['order_id' => $orderId, 'driver_token' => $driverToken];
    }

    public function test_the_customer_can_confirm_they_paid_and_not_twice(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest1');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust1');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $response = $this->actingWithToken($this->tokenFor($customer))
            ->postJson('/api/v2/orders/' . $orderId . '/confirm-payment')
            ->assertOk();
        $this->assertNotNull($response->json('data.payment_confirmations.customer_confirmed_at'));
        $this->assertNull($response->json('data.payment_confirmations.merchant_confirmed_at'));

        $this->assertNotNull(Order::find($orderId)->customer_payment_confirmed_at);

        $this->actingWithToken($this->tokenFor($customer))
            ->postJson('/api/v2/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(409);
    }

    public function test_a_stranger_cannot_confirm_someone_elses_order_payment(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest2');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust2');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $stranger = $this->makeUser(User::TYPE_CLIENT, 'Stranger2');
        $this->actingWithToken($this->tokenFor($stranger))
            ->postJson('/api/v2/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(404);
    }

    public function test_the_merchant_can_confirm_they_received_the_order_amount_and_not_twice(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest3');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust3');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $response = $this->actingWithToken($this->tokenFor($business))
            ->postJson('/api/v2/business/orders/' . $orderId . '/confirm-payment')
            ->assertOk();
        $this->assertNotNull($response->json('data.payment_confirmations.merchant_confirmed_at'));

        $this->assertNotNull(Order::find($orderId)->merchant_payment_confirmed_at);

        $this->actingWithToken($this->tokenFor($business))
            ->postJson('/api/v2/business/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(409);
    }

    public function test_another_business_cannot_confirm_payment_on_an_order_not_theirs(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest4');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust4');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $otherBusiness = $this->makeUser(User::TYPE_BUSINESS, 'Rest4b');
        $this->actingWithToken($this->tokenFor($otherBusiness))
            ->postJson('/api/v2/business/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(404);
    }

    public function test_the_driver_can_confirm_the_delivery_fee_once_the_order_is_delivered(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest5');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust5');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->deliveredOrder($business, $customer);

        $response = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')
            ->assertOk();
        $this->assertNotNull($response->json('data.driver_payment_confirmed_at'));
        $this->assertNotNull(Order::find($orderId)->driver_payment_confirmed_at);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(409);
    }

    public function test_the_driver_cannot_confirm_the_delivery_fee_before_delivery_completes(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest6');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust6');
        $orderId = $this->checkout($business, $customer, 'delivery');
        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider6');
        $driverToken = $this->tokenFor($driver);
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(409);
    }

    public function test_a_different_driver_cannot_confirm_payment_on_someone_elses_delivery(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest7');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust7');
        ['order_id' => $orderId] = $this->deliveredOrder($business, $customer);

        $otherDriver = $this->makeUser(User::TYPE_CLIENT, 'Rider7b');
        $otherDriverToken = $this->tokenFor($otherDriver);
        $this->actingWithToken($otherDriverToken)->postJson('/api/v2/delivery/register')->assertCreated();

        $this->actingWithToken($otherDriverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')
            ->assertStatus(403);
    }

    public function test_the_three_confirmations_are_independent_of_each_other(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest8');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust8');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->deliveredOrder($business, $customer);

        // Only the driver confirms — customer/merchant stay untouched.
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')->assertOk();

        $order = Order::find($orderId);
        $this->assertNotNull($order->driver_payment_confirmed_at);
        $this->assertNull($order->customer_payment_confirmed_at);
        $this->assertNull($order->merchant_payment_confirmed_at);

        $show = $this->actingWithToken($this->tokenFor($business))
            ->getJson('/api/v2/business/orders/' . $orderId)->assertOk();
        $this->assertNotNull($show->json('data.payment_confirmations.driver_confirmed_at'));
        $this->assertNull($show->json('data.payment_confirmations.customer_confirmed_at'));
    }
}
