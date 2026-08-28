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
 * A business's own private delivery-driver roster (2026-08-28), on top of the
 * pre-existing platform-wide freelance pool (see DeliveryJourneyTest). A
 * restaurant/supermarket/pharmacy links an EXISTING user by phone — never
 * mints a new account, same pattern as business_staff — and that driver then
 * only ever sees, and may only accept, THIS business's own ready orders.
 */
class BusinessDeliveryFleetTest extends TestCase
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

    /** A ready-for-pickup delivery order under $business. Returns the order id. */
    private function readyOrderFor(User $business): int
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

        return $orderId;
    }

    public function test_owner_links_an_existing_user_as_a_driver_by_phone(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');

        $this->actingAs($owner)->get('/business/delivery-drivers')->assertOk()->assertSee('موصّليّ');

        $this->actingAs($owner)->post('/business/delivery-drivers', [
            'phone' => $rider->phone,
            'vehicle_label' => 'موتوسيكل',
        ])->assertRedirect();

        $this->assertDatabaseHas('delivery_drivers', [
            'business_id' => $owner->id,
            'user_id' => $rider->id,
            'is_active' => 1,
            'vehicle_label' => 'موتوسيكل',
        ]);
    }

    public function test_an_unknown_phone_is_reported(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');

        do {
            $absent = '0100' . random_int(1000000, 9999999);
        } while (User::query()->where('phone', $absent)->exists());

        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $absent])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_driver_already_privately_linked_elsewhere_is_refused(): void
    {
        $ownerA = $this->makeUser(User::TYPE_BUSINESS, 'RestA');
        $ownerB = $this->makeUser(User::TYPE_BUSINESS, 'RestB');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');

        $this->actingAs($ownerA)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        // A second, unrelated business must not be able to silently poach it.
        $this->actingAs($ownerB)->post('/business/delivery-drivers', ['phone' => $rider->phone])
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, DeliveryDriver::query()->where('user_id', $rider->id)->count());
        $this->assertSame((int) $ownerA->id, (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('business_id'));
    }

    public function test_a_private_driver_only_sees_and_may_accept_their_own_businesss_orders(): void
    {
        $ownerA = $this->makeUser(User::TYPE_BUSINESS, 'RestA');
        $ownerB = $this->makeUser(User::TYPE_BUSINESS, 'RestB');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');

        $this->actingAs($ownerA)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $orderA = $this->readyOrderFor($ownerA);
        $orderB = $this->readyOrderFor($ownerB);

        $riderToken = $this->tokenFor($rider);
        $available = $this->actingWithToken($riderToken)
            ->getJson('/api/v2/delivery/available-orders')->assertOk()->json('data.orders');

        $ids = array_column($available, 'order_id');
        $this->assertContains($orderA, $ids, "the driver's own business order must be offered");
        $this->assertNotContains($orderB, $ids, "another business's order must never be offered to a private driver");

        // Crafting the foreign order id directly must also be refused, not
        // just hidden from the list.
        $this->actingWithToken($riderToken)
            ->postJson('/api/v2/delivery/orders/' . $orderB . '/accept')
            ->assertForbidden();

        $this->actingWithToken($riderToken)
            ->postJson('/api/v2/delivery/orders/' . $orderA . '/accept')
            ->assertCreated()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_ASSIGNED);
    }

    public function test_the_roster_shows_busy_and_idle_and_the_route(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $this->actingAs($owner)->get('/business/delivery-drivers')->assertOk()->assertSee('شاغر');

        $orderOne = $this->readyOrderFor($owner);
        $orderTwo = $this->readyOrderFor($owner);
        $riderToken = $this->tokenFor($rider);

        // One driver carrying two orders at once IS the "route".
        $this->actingWithToken($riderToken)->postJson('/api/v2/delivery/orders/' . $orderOne . '/accept')->assertCreated();
        $this->actingWithToken($riderToken)->postJson('/api/v2/delivery/orders/' . $orderTwo . '/accept')->assertCreated();

        $roster = app(DeliveryDispatchService::class)->businessRoster((int) $owner->id);
        $mine = $roster->firstWhere('user_id', $rider->id);

        $this->assertTrue($mine['busy']);
        $this->assertSame(2, $mine['active_order_count']);
        $this->assertEqualsCanonicalizing([$orderOne, $orderTwo], array_column($mine['active_orders'], 'order_id'));

        $this->actingAs($owner)->get('/business/delivery-drivers')->assertOk()->assertSee('مشغول');
    }

    public function test_owner_can_deactivate_and_reactivate_their_driver(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');

        $this->actingAs($owner)->put("/business/delivery-drivers/{$driverId}", ['is_active' => 0])->assertRedirect();
        $this->assertFalse((bool) DeliveryDriver::query()->find($driverId)->is_active);

        // Off duty means the accept endpoint refuses them, not just the UI hiding it.
        $orderId = $this->readyOrderFor($owner);
        $this->actingWithToken($this->tokenFor($rider))
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')
            ->assertForbidden();

        $this->actingAs($owner)->put("/business/delivery-drivers/{$driverId}", ['is_active' => 1])->assertRedirect();
        $this->assertTrue((bool) DeliveryDriver::query()->find($driverId)->is_active);
    }

    public function test_another_businesss_owner_cannot_toggle_a_driver_that_is_not_theirs(): void
    {
        $ownerA = $this->makeUser(User::TYPE_BUSINESS, 'RestA');
        $ownerB = $this->makeUser(User::TYPE_BUSINESS, 'RestB');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($ownerA)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $driverId = (int) DeliveryDriver::query()->where('user_id', $rider->id)->value('id');

        $this->actingAs($ownerB)->put("/business/delivery-drivers/{$driverId}", ['is_active' => 0])
            ->assertStatus(404);

        $this->assertTrue((bool) DeliveryDriver::query()->find($driverId)->is_active);
    }

    public function test_the_freelance_pool_is_unaffected_by_business_scoped_drivers(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $freelancer = $this->makeUser(User::TYPE_CLIENT, 'Freelancer');
        $freelancerToken = $this->tokenFor($freelancer);

        $this->actingWithToken($freelancerToken)->postJson('/api/v2/delivery/register')->assertCreated();

        $orderId = $this->readyOrderFor($owner);

        $available = $this->actingWithToken($freelancerToken)
            ->getJson('/api/v2/delivery/available-orders')->assertOk()->json('data.orders');

        $this->assertContains($orderId, array_column($available, 'order_id'));
    }
}
