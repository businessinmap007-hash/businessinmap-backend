<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\City;
use App\Models\Governorate;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Wiring the address book into menu checkout.
 *
 * Checkout used to take the delivery address as a free string, so a courier got
 * a line with no city, governorate or coordinates behind it. A delivery order
 * can now reference a saved address; the tests pin the three things that keep it
 * honest: the order points at the address, a readable snapshot is written for
 * the courier, and one customer cannot attach another customer's address.
 */
class CheckoutAddressBookTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private int $businessId;
    private int $menuId;
    private int $governorateId;
    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::query()->orderBy('id')->firstOrFail();
        $businessId = User::query()->where('type', 'business')->value('id');

        $governorate = Governorate::query()->whereHas('cities')->first();

        if (! $businessId || ! $governorate) {
            $this->markTestSkipped('Needs a business and a governorate with cities.');
        }

        $this->businessId = (int) $businessId;
        $this->governorateId = (int) $governorate->id;
        $this->cityId = (int) City::query()->where('governorate_id', $governorate->id)->value('id');

        $this->menuId = MenuItem::create([
            'business_id' => $this->businessId, 'name_ar' => 'صنف اختبار',
            'name_en' => 'Test', 'base_price' => 50.00, 'is_active' => 1,
        ])->id;
    }

    private function addressFor(int $userId, array $override = []): Address
    {
        return Address::create(array_merge([
            'user_id' => $userId,
            'governorate_id' => $this->governorateId,
            'city_id' => $this->cityId,
            'address_line' => 'شارع النصر، عمارة 5',
            'lat' => 30.1,
            'lng' => 31.2,
        ], $override));
    }

    private function fillCart(): void
    {
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->menuId, 'qty' => 1])
            ->assertCreated();
    }

    public function test_checkout_with_a_saved_address_points_the_order_at_it(): void
    {
        Sanctum::actingAs($this->customer);
        $address = $this->addressFor((int) $this->customer->id);
        $this->fillCart();

        $res = $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ])->assertCreated();

        $orderId = (int) $res->json('data.order.id');
        $order = Order::findOrFail($orderId);

        $this->assertSame((int) $address->id, (int) $order->delivery_address_id);
        $this->assertSame((int) $address->id, (int) $res->json('data.order.delivery_address_id'));
    }

    public function test_a_readable_snapshot_line_is_written_for_the_courier(): void
    {
        Sanctum::actingAs($this->customer);
        $address = $this->addressFor((int) $this->customer->id);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ])->assertCreated();

        $order = Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->businessId)->where('status', 'pending')->latest('id')->first();

        $this->assertStringContainsString('شارع النصر', (string) $order->address, 'the street line is snapshotted');
        $this->assertNotSame('', (string) $order->address);
    }

    public function test_the_snapshot_does_not_move_when_the_address_is_later_edited(): void
    {
        Sanctum::actingAs($this->customer);
        $address = $this->addressFor((int) $this->customer->id);
        $this->fillCart();
        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ])->assertCreated();

        $order = Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->businessId)->where('status', 'pending')->latest('id')->first();
        $snapshot = (string) $order->address;

        $address->update(['address_line' => 'عنوان مختلف تمامًا']);

        $this->assertSame($snapshot, (string) $order->fresh()->address, 'an in-flight order keeps its snapshot');
    }

    public function test_cannot_check_out_with_another_users_address(): void
    {
        $stranger = User::query()->where('id', '!=', $this->customer->id)->orderBy('id')->firstOrFail();
        $theirs = $this->addressFor((int) $stranger->id);

        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery', 'address_id' => $theirs->id,
        ])->assertStatus(422);

        // The cart is untouched — nothing was placed.
        $this->assertSame(1, Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->businessId)->where('status', 'cart')->count());
    }

    public function test_free_string_address_still_works_without_an_id(): void
    {
        Sanctum::actingAs($this->customer);
        $this->fillCart();

        $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'delivery', 'address' => 'عنوان نصي حر',
        ])->assertCreated();

        $order = Order::query()->where('user_id', $this->customer->id)
            ->where('business_id', $this->businessId)->where('status', 'pending')->latest('id')->first();

        $this->assertSame('عنوان نصي حر', (string) $order->address);
        $this->assertNull($order->delivery_address_id);
    }
}
