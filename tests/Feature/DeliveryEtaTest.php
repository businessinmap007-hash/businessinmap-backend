<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The assigned driver telling the customer roughly when the order will
 * arrive (DeliveryDispatchService::notifyEta) — either "in N minutes" or a
 * specific clock time, never both. Same driver/order setup as
 * BusinessDeliveryFleetTest.
 */
class DeliveryEtaTest extends TestCase
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

    /** @return array{order_id: int, customer: User, driver_token: string} */
    private function assignedOrder(User $business): array
    {
        $section = MenuSection::query()->create([
            'business_id' => $business->id, 'name_ar' => 'الأطباق', 'is_active' => true, 'sort_order' => 1,
        ]);
        MenuItem::query()->create([
            'business_id' => $business->id, 'menu_section_id' => $section->id,
            'name_ar' => 'كشري', 'price' => 45, 'is_active' => true, 'sort_order' => 1,
        ]);

        $customer = $this->makeUser(User::TYPE_CLIENT, 'Customer');
        $customerToken = $this->tokenFor($customer);

        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $itemId = $menu['sections'][0]['items'][0]['id'];

        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $itemId, 'qty' => 1,
        ])->assertSuccessful();

        $order = $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', [
            'fulfillment_type' => 'delivery',
            'address' => 'شارع الاختبار',
        ])->assertCreated()->json('data.order');

        $orderId = (int) $order['id'];
        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $driverToken = $this->tokenFor($driver);
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        return ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken];
    }

    public function test_the_driver_can_send_an_eta_in_minutes(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken] = $this->assignedOrder($business);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/eta', ['eta_minutes' => 20])
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'action_type' => 'open_customer_order',
        ]);
        $notif = AppNotification::where('user_id', $customer->id)->where('action_type', 'open_customer_order')->latest('id')->first();
        $this->assertSame($orderId, $notif->meta['order_id']);
        $this->assertArrayHasKey('eta_at', $notif->meta);
    }

    public function test_the_driver_can_send_an_eta_at_a_specific_time(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest2');
        ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken] = $this->assignedOrder($business);

        $etaAt = now()->addHour()->toIso8601String();

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/eta', ['eta_at' => $etaAt])
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'action_type' => 'open_customer_order',
        ]);
    }

    public function test_sending_both_eta_minutes_and_eta_at_is_rejected(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest3');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->assignedOrder($business);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/eta', [
                'eta_minutes' => 10,
                'eta_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertStatus(422);
    }

    public function test_sending_neither_is_rejected(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest4');
        ['order_id' => $orderId, 'driver_token' => $driverToken] = $this->assignedOrder($business);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/eta', [])
            ->assertStatus(422);
    }

    public function test_a_driver_not_assigned_to_the_order_cannot_send_an_eta(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest5');
        ['order_id' => $orderId] = $this->assignedOrder($business);

        $stranger = $this->makeUser(User::TYPE_CLIENT, 'Stranger');
        $strangerToken = $this->tokenFor($stranger);
        $this->actingWithToken($strangerToken)->postJson('/api/v2/delivery/register')->assertCreated();

        $this->actingWithToken($strangerToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/eta', ['eta_minutes' => 15])
            ->assertForbidden();
    }

    /** Pickup + delivery confirmation, so the on-time tests can drive a real order to STAGE_DELIVERED. */
    private function deliverOrder(int $orderId, string $businessToken, string $driverToken, string $customerToken): void
    {
        $pickupToken = $this->actingWithToken($businessToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')->assertOk()->json('data.pickup_token');
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')->assertOk();

        $deliveryToken = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')->assertOk()->json('data.delivery_token');
        $this->actingWithToken($customerToken)->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')->assertOk();
    }

    public function test_an_on_time_delivery_is_recorded_and_counted(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest6');
        ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken] = $this->assignedOrder($business);
        $customerToken = $this->tokenFor($customer);
        $businessToken = $this->tokenFor($business);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/eta', ['eta_minutes' => 60])
            ->assertOk();

        $this->deliverOrder($orderId, $businessToken, $driverToken, $customerToken);

        $this->assertDatabaseHas('delivery_completions', ['order_id' => $orderId, 'on_time' => 1]);
        $order = \App\Models\Order::find($orderId);
        $driver = \App\Models\DeliveryDriver::where('id', $order->delivery_driver_id)->first();
        $this->assertSame(1, $driver->fast_delivery_count);
    }

    public function test_a_late_delivery_is_recorded_as_not_on_time(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest7');
        ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken] = $this->assignedOrder($business);
        $customerToken = $this->tokenFor($customer);
        $businessToken = $this->tokenFor($business);

        // An ETA that has already passed by the time delivery is confirmed.
        \App\Models\Order::where('id', $orderId)->update(['delivery_eta_at' => now()->subMinute()]);

        $this->deliverOrder($orderId, $businessToken, $driverToken, $customerToken);

        $this->assertDatabaseHas('delivery_completions', ['order_id' => $orderId, 'on_time' => 0]);
        $order = \App\Models\Order::find($orderId);
        $driver = \App\Models\DeliveryDriver::where('id', $order->delivery_driver_id)->first();
        $this->assertSame(0, $driver->fast_delivery_count);
    }

    public function test_a_delivery_with_no_eta_has_no_on_time_verdict(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest8');
        ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken] = $this->assignedOrder($business);
        $customerToken = $this->tokenFor($customer);
        $businessToken = $this->tokenFor($business);

        // No notifyEta() call at all for this order.
        $this->deliverOrder($orderId, $businessToken, $driverToken, $customerToken);

        $completion = \App\Models\DeliveryCompletion::where('order_id', $orderId)->firstOrFail();
        $this->assertNull($completion->on_time);
    }
}
