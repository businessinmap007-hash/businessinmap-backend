<?php

namespace Tests\Feature;

use App\Models\DeliveryCompletion;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The connected delivery loop, walked by all three parties at once.
 *
 * This is the hardest journey in the app: a customer, a restaurant and a driver
 * each hold one piece, and the order only moves when the right party presents
 * the right token at the right stage. `delivery_orders` holds zero rows — of the
 * six services only booking has ever run — so nothing here has been proven end
 * to end before.
 *
 * The rule, as in MenuOrderJourneyTest: every id the client uses comes out of a
 * previous API RESPONSE. The driver learns the order from `available-orders`,
 * never from the customer's checkout. The two tokens are the one legitimate
 * hand-off: they travel through the physical world as a QR on someone's screen,
 * so passing a token from one actor's response into the other's request IS the
 * scan. What is never allowed is an actor holding an id the API did not give
 * them.
 *
 * Rolls back.
 */
class DeliveryJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'secret-password';

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = $this->seedRestaurantWithAMenu();
    }

    /**
     * Laravel caches the resolved user for the whole test method, so swapping
     * the Bearer header does NOT re-authenticate — the first identity sticks
     * silently. With three actors in one method this is not optional: without
     * it, the driver's requests would still be the customer's. See
     * MenuOrderJourneyTest.
     */
    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function makeUser(string $name, string $type = User::TYPE_CLIENT): User
    {
        $user = new User();
        $user->name = $name;
        $user->email = 'deliv-' . uniqid() . '@example.test';
        $user->phone = '0102' . random_int(1000000, 9999999);
        $user->password = self::PASSWORD;
        $user->type = $type;
        $user->api_token = Str::random(80);
        $user->save();

        return $user->fresh();
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }

    /** The merchant's own setup — that is the merchant's journey, not the customer's. */
    private function seedRestaurantWithAMenu(): User
    {
        $business = $this->makeUser('مطعم التوصيل', User::TYPE_BUSINESS);

        $section = MenuSection::query()->create([
            'business_id' => $business->id,
            'name_ar' => 'الأطباق',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        MenuItem::query()->create([
            'business_id' => $business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'كشري',
            'price' => 45,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $business;
    }

    /**
     * A customer orders delivery and the restaurant starts cooking — the state
     * every driver test needs. Returns [customerToken, orderId].
     *
     * @return array{0:string,1:int}
     */
    private function anOrderWaitingForADriver(): array
    {
        $customer = $this->makeUser('عميل التوصيل');
        $token = $this->tokenFor($customer);

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)->assertOk()->json('data');
        $itemId = $menu['sections'][0]['items'][0]['id'];

        $this->actingWithToken($token)->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $itemId,
            'qty' => 1,
        ])->assertSuccessful();

        // NOTE: checkout takes `address` as a free string, not an address_id —
        // the address book (BIM-11.1) is not wired to it yet. Recorded in
        // docs/06_ENGINEERING_REFERENCE.md §9.
        $order = $this->actingWithToken($token)->postJson('/api/v2/cart/' . $this->business->id . '/checkout', [
            'fulfillment_type' => 'delivery',
            'address' => '12 شارع الجمهورية، وسط البلد',
        ])->assertCreated()->json('data.order');

        $orderId = (int) $order['id'];

        // The restaurant takes it and starts cooking. A driver may only see it
        // once it is being prepared — that is what makes it dispatchable.
        $businessToken = $this->tokenFor($this->business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        return [$token, $orderId];
    }

    /** Register a driver and return their bearer token. */
    private function aDriver(string $name = 'موصّل'): string
    {
        $token = $this->tokenFor($this->makeUser($name));

        $this->actingWithToken($token)->postJson('/api/v2/delivery/register', [
            'vehicle_label' => 'موتوسيكل',
        ])->assertCreated();

        return $token;
    }

    public function test_a_meal_travels_from_the_kitchen_to_the_customer_through_both_scans(): void
    {
        [$customerToken, $orderId] = $this->anOrderWaitingForADriver();
        $driverToken = $this->aDriver();

        // ── The driver finds the job. This is the only way they may learn the
        // order id: they were never told it by the customer.
        $available = $this->actingWithToken($driverToken)
            ->getJson('/api/v2/delivery/available-orders')
            ->assertOk()
            ->json('data.orders');

        $this->assertContains(
            $orderId,
            array_column($available, 'order_id'),
            'a delivery order being prepared must be offered to drivers, or it can never be delivered'
        );

        $job = collect($available)->firstWhere('order_id', $orderId);
        $this->assertNotEmpty($job['address'], 'a driver who cannot see where to go cannot deliver');
        $this->assertNotNull($job['business']['name'] ?? null, 'the driver must know which restaurant to go to');

        // ── Take it.
        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $job['order_id'] . '/accept')
            ->assertCreated()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_ASSIGNED);

        // ── Once taken, it must disappear from every other driver's list, or two
        // drivers turn up for one meal.
        $secondDriver = $this->aDriver('موصّل آخر');
        $stillOffered = $this->actingWithToken($secondDriver)
            ->getJson('/api/v2/delivery/available-orders')
            ->assertOk()
            ->json('data.orders');

        $this->assertNotContains(
            $orderId,
            array_column($stillOffered, 'order_id'),
            'an assigned order must not still be on offer'
        );

        // ── Stage 1: the restaurant shows its pickup QR, the driver scans it.
        $pickupToken = $this->actingWithToken($this->tokenFor($this->business))
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')
            ->assertOk()
            ->json('data.pickup_token');

        $this->assertNotEmpty($pickupToken);

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')
            ->assertOk()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_PICKED_UP);

        // ── Stage 2: the driver shows their delivery QR, the customer scans it.
        $deliveryToken = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')
            ->assertOk()
            ->json('data.delivery_token');

        $this->assertNotEmpty($deliveryToken);

        $this->actingWithToken($customerToken)
            ->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryDispatchService::STATUS_COMPLETED)
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_DELIVERED);

        // ── The ledger row is the whole point of the loop: it is the recorded
        // success for BOTH the restaurant and the driver.
        $this->assertDatabaseHas('delivery_completions', [
            'order_id' => $orderId,
            'business_id' => $this->business->id,
        ]);

        $completion = DeliveryCompletion::query()->where('order_id', $orderId)->first();
        $this->assertNotNull($completion->driver_user_id, 'a completion nobody is credited for is not a completion');
    }

    public function test_a_scanned_token_cannot_be_scanned_again(): void
    {
        [$customerToken, $orderId] = $this->anOrderWaitingForADriver();
        $driverToken = $this->aDriver();

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $pickupToken = $this->actingWithToken($this->tokenFor($this->business))
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')->assertOk()->json('data.pickup_token');

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')->assertOk();

        // A photographed QR must be worthless the moment it is used.
        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')
            ->assertNotFound();

        $deliveryToken = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')->assertOk()->json('data.delivery_token');

        $this->actingWithToken($customerToken)
            ->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')->assertOk();

        $this->actingWithToken($customerToken)
            ->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')
            ->assertNotFound();
    }

    public function test_a_token_is_worthless_in_the_wrong_hands(): void
    {
        [, $orderId] = $this->anOrderWaitingForADriver();

        $driverToken = $this->aDriver();
        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $pickupToken = $this->actingWithToken($this->tokenFor($this->business))
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')->assertOk()->json('data.pickup_token');

        // Another driver who somehow sees the restaurant's QR must not be able to
        // collect a meal that is not theirs.
        $this->actingWithToken($this->aDriver('موصّل متطفل'))
            ->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')
            ->assertForbidden();

        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/pickup/' . $pickupToken . '/confirm')->assertOk();

        $deliveryToken = $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')->assertOk()->json('data.delivery_token');

        // And a stranger must not be able to mark someone else's meal received —
        // that would strand the real customer with a completed order.
        $this->actingWithToken($this->tokenFor($this->makeUser('غريب')))
            ->postJson('/api/v2/delivery/deliver/' . $deliveryToken . '/confirm')
            ->assertForbidden();

        $this->assertSame(
            DeliveryDispatchService::STAGE_PICKED_UP,
            (string) Order::query()->find($orderId)->delivery_stage,
            'a refused scan must not have moved the order'
        );
    }

    public function test_the_stages_cannot_be_skipped(): void
    {
        [, $orderId] = $this->anOrderWaitingForADriver();
        $driverToken = $this->aDriver();

        // No driver assigned yet: there is nobody to hand the food to.
        $this->actingWithToken($this->tokenFor($this->business))
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/pickup-token')
            ->assertStatus(422);

        $this->actingWithToken($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        // Assigned, but the food is still on the counter — a driver who could
        // issue the delivery QR here could be paid for a meal they never carried.
        $this->actingWithToken($driverToken)
            ->postJson('/api/v2/delivery/orders/' . $orderId . '/delivery-token')
            ->assertStatus(422);
    }

    public function test_only_a_registered_driver_can_see_the_job_board(): void
    {
        $this->anOrderWaitingForADriver();

        $this->actingWithToken($this->tokenFor($this->makeUser('عميل عادي')))
            ->getJson('/api/v2/delivery/available-orders')
            ->assertForbidden();
    }
}
