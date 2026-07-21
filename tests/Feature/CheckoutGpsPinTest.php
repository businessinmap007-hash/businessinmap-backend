<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A one-off GPS delivery pin at checkout — "I'm with friends somewhere other
 * than my saved address, deliver here for THIS order only".
 *
 * The pin rides on the order (delivery_lat/lng), is resolved to a readable city
 * line via our own `cities` table (no map provider), and never touches the
 * account's saved addresses. address_id, when present, still wins.
 */
class CheckoutGpsPinTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private int $businessId;
    private int $menuId;
    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::query()->orderBy('id')->firstOrFail();
        $businessId = User::query()->where('type', 'business')->value('id');

        $city = City::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('latitude', '!=', 0)->where('longitude', '!=', 0)
            ->whereHas('governorate')
            ->first();

        if (! $businessId || ! $city) {
            $this->markTestSkipped('Needs a business and a city with coordinates.');
        }

        $this->businessId = (int) $businessId;
        $this->city = $city;

        $this->menuId = MenuItem::create([
            'business_id' => $this->businessId, 'name_ar' => 'صنف اختبار',
            'name_en' => 'Test', 'base_price' => 50.00, 'is_active' => 1,
        ])->id;
    }

    private function fillCart(): void
    {
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->menuId, 'qty' => 1])
            ->assertCreated();
    }

    private function placedOrder(): Order
    {
        return Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->businessId)->where('status', 'pending')->latest('id')->firstOrFail();
    }

    public function test_a_gps_pin_is_stored_and_resolved_to_a_city_line(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $res = $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery',
            'lat' => (float) $this->city->latitude,
            'lng' => (float) $this->city->longitude,
        ])->assertCreated();

        // The coordinates are echoed back and stored on the order.
        $this->assertEqualsWithDelta((float) $this->city->latitude, (float) $res->json('data.order.delivery_lat'), 0.0001);
        $this->assertEqualsWithDelta((float) $this->city->longitude, (float) $res->json('data.order.delivery_lng'), 0.0001);

        $order = $this->placedOrder();
        $this->assertEqualsWithDelta((float) $this->city->latitude, (float) $order->delivery_lat, 0.0001);
        $this->assertNull($order->delivery_address_id, 'a GPS pin is not a saved address');

        // Standing on the city, the readable line names it for the courier.
        $cityName = $this->city->name_ar ?: $this->city->name_en;
        $this->assertStringContainsString((string) $cityName, (string) $order->address);
    }

    public function test_a_free_note_is_kept_alongside_the_resolved_city(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery',
            'lat' => (float) $this->city->latitude,
            'lng' => (float) $this->city->longitude,
            'address' => 'بجوار المسجد، الدور الثالث',
        ])->assertCreated();

        $order = $this->placedOrder();
        $this->assertStringContainsString('بجوار المسجد', (string) $order->address, 'the customer note survives');
    }

    public function test_a_saved_address_id_wins_over_a_pin(): void
    {
        Sanctum::actingAs($this->customer);
        $address = $this->customer->addresses()->create([
            'governorate_id' => (int) $this->city->governorate_id,
            'city_id' => (int) $this->city->id,
            'address_line' => 'شارع النصر، عمارة 5',
            'lat' => 30.1, 'lng' => 31.2,
        ]);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery',
            'address_id' => $address->id,
            'lat' => (float) $this->city->latitude,
            'lng' => (float) $this->city->longitude,
        ])->assertCreated();

        $order = $this->placedOrder();
        $this->assertSame((int) $address->id, (int) $order->delivery_address_id);
        $this->assertNull($order->delivery_lat, 'the saved address wins; no loose pin is kept');
    }

    public function test_lat_without_lng_is_rejected(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery',
            'lat' => (float) $this->city->latitude,
        ])->assertStatus(422);
    }
}
