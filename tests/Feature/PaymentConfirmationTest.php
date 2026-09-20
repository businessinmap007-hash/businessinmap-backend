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

    public function test_a_delivered_order_stays_in_the_drivers_list_until_the_fee_is_confirmed(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestL');
        $this->seedMenu($business);
        $this->actingWithToken($this->tokenFor($business))
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])->assertOk();
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustL');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->deliveredOrder($business, $customer);

        $ids = fn () => collect($this->actingWithToken($driverToken)->getJson('/api/v2/delivery/my-orders')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($orderId, $ids());

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->assertNotContains($orderId, $ids());
    }

    public function test_drivers_are_notified_when_a_delivery_order_becomes_available(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestN');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustN');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'RiderN');
        $riderToken = $this->tokenFor($rider);
        $this->actingWithToken($riderToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $off = $this->makeUser(User::TYPE_CLIENT, 'RiderOff');
        $offToken = $this->tokenFor($off);
        $this->actingWithToken($offToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($offToken)->postJson('/api/v2/delivery/availability', ['is_active' => false])->assertOk();

        $orderId = $this->checkout($business, $customer, 'delivery');
        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();

        $count = fn ($user) => \App\Models\AppNotification::query()
            ->where('user_id', $user->id)->where('notifiable_id', $orderId)->where('action_type', 'open_available_orders')->count();
        $this->assertSame(0, $count($rider));

        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        $this->assertSame(1, $count($rider));
        $this->assertSame(0, $count($off));
    }

    public function test_the_merchant_can_reset_the_pickup_code_and_the_old_one_stops_working(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestR');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustR');
        $orderId = $this->checkout($business, $customer, 'delivery');
        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();
        $driver = $this->makeUser(User::TYPE_CLIENT, 'RiderR');
        $driverToken = $this->tokenFor($driver);
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $old = $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/pickup-token')->assertOk()->json('data.pickup_token');
        $new = $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/pickup-token/reset')->assertOk()->json('data.pickup_token');
        $this->assertNotSame($old, $new);

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/pickup/' . $old . '/confirm')->assertStatus(404);
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/pickup/' . $new . '/confirm')->assertOk();
    }

    public function test_another_business_cannot_reset_a_pickup_code_it_does_not_own(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestR2');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustR2');
        $orderId = $this->checkout($business, $customer, 'delivery');
        $other = $this->makeUser(User::TYPE_BUSINESS, 'RestR2b');

        $this->actingWithToken($this->tokenFor($other))
            ->postJson('/api/v2/business/orders/' . $orderId . '/pickup-token/reset')->assertStatus(403);
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

    public function test_a_pickup_order_settles_once_customer_and_merchant_both_confirm(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest9');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust9');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $this->actingWithToken($this->tokenFor($customer))->postJson('/api/v2/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->assertNull(Order::find($orderId)->payment_settled_at);

        $response = $this->actingWithToken($this->tokenFor($business))
            ->postJson('/api/v2/business/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->assertNotNull(Order::find($orderId)->payment_settled_at);
        $this->assertNotNull($response->json('data.payment_confirmations.settled_at'));
    }

    public function test_a_delivery_order_with_a_fee_needs_the_driver_too_before_it_settles(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest10');
        $this->seedMenu($business);
        $this->actingWithToken($this->tokenFor($business))
            ->patchJson('/api/v2/business/delivery-settings', ['delivery_fee_amount' => 30])->assertOk();
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust10');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->deliveredOrder($business, $customer);

        $this->actingWithToken($this->tokenFor($customer))->postJson('/api/v2/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->actingWithToken($this->tokenFor($business))->postJson('/api/v2/business/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->assertNull(Order::find($orderId)->payment_settled_at);

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->assertNotNull(Order::find($orderId)->payment_settled_at);
    }

    public function test_a_staff_member_with_orders_capability_is_notified_and_can_work_the_order(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestS');
        $this->seedMenu($business);
        $staff = $this->makeUser(User::TYPE_CLIENT, 'StaffS');
        \App\Models\BusinessStaff::query()->create([
            'business_id' => $business->id, 'user_id' => $staff->id, 'title' => 'cashier',
            'capabilities' => ['orders'], 'is_active' => true, 'status' => \App\Models\BusinessStaff::STATUS_ACCEPTED,
        ]);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustS');
        $orderId = $this->checkout($business, $customer, 'pickup');

        $this->assertTrue(
            \App\Models\AppNotification::query()->where('user_id', $staff->id)->where('notifiable_id', $orderId)->exists()
        );

        $staffToken = $this->tokenFor($staff);
        $this->actingWithToken($staffToken)->withHeader('X-Business-Id', (string) $business->id)
            ->getJson('/api/v2/business/orders')->assertOk()->assertJsonPath('data.0.id', $orderId);
        $this->actingWithToken($staffToken)->withHeader('X-Business-Id', (string) $business->id)
            ->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
    }

    public function test_a_cash_pickup_order_needs_the_merchant_confirmation_to_complete_and_settlement_to_be_reviewed(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestG');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustG');
        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $menu['sections'][0]['items'][0]['id'], 'qty' => 1,
        ])->assertSuccessful();
        $orderId = (int) $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', [
            'fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String(), 'payment_method' => 'cash',
        ])->assertCreated()->json('data.order.id');

        $businessToken = $this->tokenFor($business);
        foreach (['accept', 'preparing', 'ready'] as $step) {
            $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/' . $step)->assertSuccessful();
        }

        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/complete')->assertStatus(409);

        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/complete')->assertSuccessful();

        $review = ['operation_type' => 'order', 'operation_id' => $orderId, 'stars' => 5];
        $this->actingWithToken($customerToken)->postJson('/api/v2/ratings/review', $review)->assertStatus(409);

        $this->actingWithToken($customerToken)->postJson('/api/v2/orders/' . $orderId . '/confirm-payment')->assertOk();
        $this->actingWithToken($customerToken)->postJson('/api/v2/ratings/review', $review)->assertStatus(201);
    }

    public function test_an_order_with_no_payment_method_is_not_held_to_the_confirmation(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestG2');
        $this->seedMenu($business);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustG2');
        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $menu['sections'][0]['items'][0]['id'], 'qty' => 1,
        ])->assertSuccessful();
        $orderId = (int) $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', [
            'fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String(), 'payment_method' => 'card',
        ])->assertCreated()->json('data.order.id');
        $businessToken = $this->tokenFor($business);
        foreach (['accept', 'preparing', 'ready', 'complete'] as $step) {
            $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/' . $step)->assertSuccessful();
        }
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
