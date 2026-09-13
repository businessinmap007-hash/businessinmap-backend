<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The merchant hands a ready delivery order directly to one of its OWN
 * roster drivers — the counterpart to the driver self-selecting from the
 * open pool (see BusinessDeliveryFleetTest). Both paths land on the exact
 * same STAGE_ASSIGNED state, so the rest of the pickup/delivery QR loop
 * (DeliveryJourneyTest) runs identically either way.
 */
class BusinessDriverAssignmentTest extends TestCase
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
        $u->api_token = Str::random(80);

        if ($type === User::TYPE_BUSINESS) {
            $u->category_id = self::RESTAURANT_ROOT;
            $u->category_child_id = self::RESTAURANT_CHILD;
        }

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

    /** A ready-for-pickup delivery order under $business, placed by a fresh customer. */
    private function readyOrderFor(User $business): array
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
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/ready')->assertSuccessful();

        return ['order_id' => $orderId, 'customer' => $customer, 'business_token' => $businessToken];
    }

    public function test_business_can_assign_its_own_driver_to_a_ready_order(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');

        ['order_id' => $orderId, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverId])
            ->assertOk()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_ASSIGNED);

        $this->assertSame($driverId, (int) Order::query()->find($orderId)->delivery_driver_id);
    }

    public function test_business_cannot_assign_a_driver_that_isnt_its_own(): void
    {
        $ownerA = $this->makeUser(User::TYPE_BUSINESS, 'RestA');
        $ownerB = $this->makeUser(User::TYPE_BUSINESS, 'RestB');
        $riderB = $this->makeUser(User::TYPE_CLIENT, 'RiderB');
        $this->actingAs($ownerB)->post('/business/delivery-drivers', ['phone' => $riderB->phone])->assertRedirect();
        $driverBId = (int) DeliveryDriver::query()->where('user_id', $riderB->id)->value('id');

        ['order_id' => $orderId, 'business_token' => $businessTokenA] = $this->readyOrderFor($ownerA);

        $this->actingWithToken($businessTokenA)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverBId])
            ->assertStatus(404);

        $this->assertNull(Order::query()->find($orderId)->delivery_driver_id);
    }

    public function test_business_cannot_assign_a_freelance_driver(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $freelancer = $this->makeUser(User::TYPE_CLIENT, 'Freelancer');
        $this->actingWithToken($this->tokenFor($freelancer))->postJson('/api/v2/delivery/register')->assertCreated();
        $freelancerDriverId = (int) DeliveryDriver::query()->where('user_id', $freelancer->id)->value('id');

        ['order_id' => $orderId, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $freelancerDriverId])
            ->assertStatus(404);
    }

    public function test_business_cannot_assign_an_inactive_driver(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');
        $this->actingAs($owner)->put("/business/delivery-drivers/{$driverId}", ['is_active' => 0])->assertRedirect();

        ['order_id' => $orderId, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_id');
    }

    public function test_an_already_assigned_order_cannot_be_assigned_again(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $riderOne = $this->makeUser(User::TYPE_CLIENT, 'RiderOne');
        $riderTwo = $this->makeUser(User::TYPE_CLIENT, 'RiderTwo');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $riderOne->phone])->assertRedirect();
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $riderTwo->phone])->assertRedirect();
        $driverOneId = (int) DeliveryDriver::query()->where('user_id', $riderOne->id)->value('id');
        $driverTwoId = (int) DeliveryDriver::query()->where('user_id', $riderTwo->id)->value('id');

        ['order_id' => $orderId, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverOneId])
            ->assertOk();

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverTwoId])
            ->assertStatus(409);
    }

    /** The assigned driver now sees the order in their own active list, full invoice included. */
    public function test_the_assigned_driver_sees_the_order_with_full_invoice(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');

        ['order_id' => $orderId, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverId])
            ->assertOk();

        $riderToken = $this->tokenFor($rider);

        $myOrders = $this->actingWithToken($riderToken)->getJson('/api/v2/delivery/my-orders')->assertOk()->json('data');
        $ids = array_column($myOrders, 'id');
        $this->assertContains($orderId, $ids, 'the assigned driver must see the order in their active list');

        $mine = collect($myOrders)->firstWhere('id', $orderId);
        $this->assertNotEmpty($mine['items'], 'the driver must see the full invoice lines');
        $this->assertSame('شارع الاختبار', $mine['address']);

        // And the generic order-detail endpoint (OrderPolicy) must now also
        // let the driver through — the same invoice a business or customer sees.
        $this->actingWithToken($riderToken)
            ->getJson("/api/v2/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    }

    /** A driver NOT assigned to the order is still refused — the policy change is scoped, not a blanket grant. */
    public function test_an_unassigned_driver_still_cannot_view_the_order(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $assignedRider = $this->makeUser(User::TYPE_CLIENT, 'Assigned');
        $strangerRider = $this->makeUser(User::TYPE_CLIENT, 'Stranger');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $assignedRider->phone])->assertRedirect();
        $this->actingWithToken($this->tokenFor($strangerRider))->postJson('/api/v2/delivery/register')->assertCreated();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $assignedRider->id)->value('id');

        ['order_id' => $orderId, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverId])
            ->assertOk();

        $this->actingWithToken($this->tokenFor($strangerRider))
            ->getJson("/api/v2/orders/{$orderId}")
            ->assertForbidden();
    }

    /** The roster response includes each driver's live distance once the business has a saved location. */
    public function test_the_roster_includes_driver_distance_when_the_business_has_a_location(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $owner->forceFill(['latitude' => 30.0444, 'longitude' => 31.2357])->save(); // Cairo

        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');

        DeliveryDriver::query()->whereKey($driverId)->update([
            'last_lat' => 30.0500, 'last_lng' => 31.2400, 'location_updated_at' => now(),
        ]);

        $businessToken = $this->tokenFor($owner);
        $drivers = $this->actingWithToken($businessToken)
            ->getJson('/api/v2/business/delivery-drivers')->assertOk()->json('data.drivers');

        $mine = collect($drivers)->firstWhere('id', $driverId);
        $this->assertNotNull($mine['distance_km'], 'a driver with a fresh location must show a distance once the business has one too');
    }

    /**
     * End to end: a business-assigned driver runs the exact same pickup/delivery
     * QR loop as a self-selected one (DeliveryJourneyTest covers that path) —
     * assignDriver() and acceptOrder() must be interchangeable from here on.
     */
    public function test_the_full_pickup_and_delivery_loop_works_after_a_business_assignment(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');

        ['order_id' => $orderId, 'customer' => $customer, 'business_token' => $businessToken] = $this->readyOrderFor($owner);

        $this->actingWithToken($businessToken)
            ->postJson("/api/v2/business/orders/{$orderId}/assign-driver", ['driver_id' => $driverId])
            ->assertOk();

        $pickupToken = $this->actingWithToken($businessToken)
            ->postJson("/api/v2/delivery/orders/{$orderId}/pickup-token")
            ->assertOk()->json('data.pickup_token');

        $riderToken = $this->tokenFor($rider);
        $this->actingWithToken($riderToken)
            ->postJson("/api/v2/delivery/pickup/{$pickupToken}/confirm")
            ->assertOk()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_PICKED_UP);

        $deliveryToken = $this->actingWithToken($riderToken)
            ->postJson("/api/v2/delivery/orders/{$orderId}/delivery-token")
            ->assertOk()->json('data.delivery_token');

        $customerToken = $this->tokenFor($customer);
        $this->actingWithToken($customerToken)
            ->postJson("/api/v2/delivery/deliver/{$deliveryToken}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_DELIVERED);
    }

    /** GET /delivery/me is read-only: a user who never registered gets null, not a freshly-minted row. */
    public function test_a_never_registered_user_reads_null_driver_status(): void
    {
        $user = $this->makeUser(User::TYPE_CLIENT, 'Nobody');

        $this->actingWithToken($this->tokenFor($user))
            ->getJson('/api/v2/delivery/me')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSame(0, DeliveryDriver::query()->where('user_id', $user->id)->count());
    }

    /** GET /delivery/me must never reactivate a driver a business explicitly turned off. */
    public function test_checking_status_never_reactivates_a_deactivated_driver(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();
        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');
        $this->actingAs($owner)->put("/business/delivery-drivers/{$driverId}", ['is_active' => 0])->assertRedirect();

        $riderToken = $this->tokenFor($rider);

        $this->actingWithToken($riderToken)
            ->getJson('/api/v2/delivery/me')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Checking again changes nothing.
        $this->actingWithToken($riderToken)->getJson('/api/v2/delivery/me')->assertOk();

        $this->assertFalse((bool) DeliveryDriver::query()->find($driverId)->is_active);
    }
}
