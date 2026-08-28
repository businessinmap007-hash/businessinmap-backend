<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use App\Services\DeliveryDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Neither the freelance pool nor a business's own private driver had any
 * live position at all — a 2026-08-28 follow-up to the driver-fleet feature.
 * A driver pings /delivery/location every 30-60s while carrying an active
 * order; a business sees the distance from that ping to the restaurant
 * (before pickup) and to the customer (after pickup), and can browse
 * freelance drivers within a radius of its own location — visibility only,
 * a freelance driver still self-selects from the job board.
 */
class DeliveryLiveLocationTest extends TestCase
{
    use DatabaseTransactions;

    private const RESTAURANT_CHILD = 245;

    private const RESTAURANT_ROOT = 16;

    // Cairo-ish coordinates, close enough together for a small, predictable radius.
    private const RESTAURANT_LAT = 30.0500;

    private const RESTAURANT_LNG = 31.2400;

    private const NEAR_LAT = 30.0600; // ~1.2km from the restaurant

    private const NEAR_LNG = 31.2450;

    private const FAR_LAT = 30.4000; // ~40km from the restaurant

    private const FAR_LNG = 31.5000;

    private function makeUser(string $type, string $tag, array $extra = []): User
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

        foreach ($extra as $k => $v) {
            $u->{$k} = $v;
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

    public function test_a_driver_can_ping_their_location(): void
    {
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $token = $this->tokenFor($rider);
        $this->actingWithToken($token)->postJson('/api/v2/delivery/register')->assertCreated();

        $this->actingWithToken($token)->postJson('/api/v2/delivery/location', [
            'lat' => self::NEAR_LAT, 'lng' => self::NEAR_LNG,
        ])->assertOk();

        $driver = DeliveryDriver::query()->where('user_id', $rider->id)->first();
        $this->assertEqualsWithDelta(self::NEAR_LAT, (float) $driver->last_lat, 0.0001);
        $this->assertNotNull($driver->location_updated_at);
    }

    public function test_a_stranger_cannot_ping_without_registering(): void
    {
        $stranger = $this->makeUser(User::TYPE_CLIENT, 'Stranger');

        $this->actingWithToken($this->tokenFor($stranger))
            ->postJson('/api/v2/delivery/location', ['lat' => 30.0, 'lng' => 31.0])
            ->assertForbidden();
    }

    public function test_the_roster_shows_distance_to_restaurant_before_pickup(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest', [
            'latitude' => self::RESTAURANT_LAT, 'longitude' => self::RESTAURANT_LNG,
        ]);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $orderId = $this->readyOrderFor($owner);
        $riderToken = $this->tokenFor($rider);
        $this->actingWithToken($riderToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();
        $this->actingWithToken($riderToken)->postJson('/api/v2/delivery/location', [
            'lat' => self::NEAR_LAT, 'lng' => self::NEAR_LNG,
        ])->assertOk();

        $roster = app(DeliveryDispatchService::class)->businessRoster((int) $owner->id, self::RESTAURANT_LAT, self::RESTAURANT_LNG);
        $mine = $roster->firstWhere('user_id', $rider->id);
        $order = collect($mine['active_orders'])->firstWhere('order_id', $orderId);

        $this->assertTrue($mine['location_available']);
        $this->assertNotNull($order['distance_to_restaurant_km']);
        $this->assertLessThan(5, $order['distance_to_restaurant_km'], 'the two points are ~1.2km apart');
        // Not yet picked up — no meaningful "distance to customer" to show.
        $this->assertNull($order['distance_to_customer_km']);
    }

    public function test_a_driver_who_never_pinged_shows_no_distance(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest', [
            'latitude' => self::RESTAURANT_LAT, 'longitude' => self::RESTAURANT_LNG,
        ]);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $orderId = $this->readyOrderFor($owner);
        $this->actingWithToken($this->tokenFor($rider))->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        $roster = app(DeliveryDispatchService::class)->businessRoster((int) $owner->id, self::RESTAURANT_LAT, self::RESTAURANT_LNG);
        $mine = $roster->firstWhere('user_id', $rider->id);

        $this->assertFalse($mine['location_available']);
        $this->assertNull($mine['active_orders'][0]['distance_to_restaurant_km']);
    }

    public function test_nearby_freelancers_are_found_within_radius_and_excluded_beyond_it(): void
    {
        $near = $this->makeUser(User::TYPE_CLIENT, 'NearRider');
        $far = $this->makeUser(User::TYPE_CLIENT, 'FarRider');
        $stale = $this->makeUser(User::TYPE_CLIENT, 'StaleRider');

        $nearToken = $this->tokenFor($near);
        $farToken = $this->tokenFor($far);
        $staleToken = $this->tokenFor($stale);

        $this->actingWithToken($nearToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($farToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($staleToken)->postJson('/api/v2/delivery/register')->assertCreated();

        $this->actingWithToken($nearToken)->postJson('/api/v2/delivery/location', ['lat' => self::NEAR_LAT, 'lng' => self::NEAR_LNG])->assertOk();
        $this->actingWithToken($farToken)->postJson('/api/v2/delivery/location', ['lat' => self::FAR_LAT, 'lng' => self::FAR_LNG])->assertOk();

        // A stale ping (older than the freshness window) must not count as a position.
        DeliveryDriver::query()->where('user_id', $stale->id)->update([
            'last_lat' => self::NEAR_LAT,
            'last_lng' => self::NEAR_LNG,
            'location_updated_at' => now()->subMinutes(DeliveryDriver::LOCATION_STALE_AFTER_MINUTES + 5),
        ]);

        $nearby = app(DeliveryDispatchService::class)
            ->nearbyFreelanceDrivers(self::RESTAURANT_LAT, self::RESTAURANT_LNG, 5.0);

        $ids = $nearby->pluck('user_id')->all();
        $this->assertContains($near->id, $ids, 'a nearby, fresh, on-duty freelancer must be found');
        $this->assertNotContains($far->id, $ids, 'a freelancer far outside the radius must not be found');
        $this->assertNotContains($stale->id, $ids, 'a stale ping must not count as a live position');
    }

    public function test_a_business_owned_driver_never_appears_in_the_freelance_pool_search(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest', [
            'latitude' => self::RESTAURANT_LAT, 'longitude' => self::RESTAURANT_LNG,
        ]);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingAs($owner)->post('/business/delivery-drivers', ['phone' => $rider->phone])->assertRedirect();

        $this->actingWithToken($this->tokenFor($rider))->postJson('/api/v2/delivery/location', [
            'lat' => self::NEAR_LAT, 'lng' => self::NEAR_LNG,
        ])->assertOk();

        $nearby = app(DeliveryDispatchService::class)
            ->nearbyFreelanceDrivers(self::RESTAURANT_LAT, self::RESTAURANT_LNG, 5.0);

        $this->assertNotContains($rider->id, $nearby->pluck('user_id')->all());
    }

    public function test_the_panel_shows_nearby_freelancers_for_a_located_business(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest', [
            'latitude' => self::RESTAURANT_LAT, 'longitude' => self::RESTAURANT_LNG,
        ]);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $this->actingWithToken($this->tokenFor($rider))->postJson('/api/v2/delivery/register')->assertCreated();
        $this->actingWithToken($this->tokenFor($rider))->postJson('/api/v2/delivery/location', [
            'lat' => self::NEAR_LAT, 'lng' => self::NEAR_LNG,
        ])->assertOk();

        $this->actingAs($owner)->get('/business/delivery-drivers?radius_km=5')
            ->assertOk()
            ->assertSee('الموصّلون الأحرار القريبون');
    }
}
