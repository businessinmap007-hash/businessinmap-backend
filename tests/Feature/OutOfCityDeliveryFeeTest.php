<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\DeliveryDriver;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\PromotesCarriers;
use Tests\TestCase;

/**
 * Out-of-city delivery is priced per order: the courier proposes an amount, the
 * customer accepts or declines it, and the merchant recommends it as suitable
 * or not. In-city orders keep the business's fixed fee.
 */
class OutOfCityDeliveryFeeTest extends TestCase
{
    use DatabaseTransactions;
    use PromotesCarriers;

    private const RESTAURANT_CHILD = 245;

    private const RESTAURANT_ROOT = 16;

    private int $cityA;

    private int $cityB;

    protected function setUp(): void
    {
        parent::setUp();

        $ids = DB::table('cities')->orderBy('id')->limit(2)->pluck('id')->all();
        $this->cityA = (int) $ids[0];
        $this->cityB = (int) $ids[1];
    }

    private function makeUser(string $type, string $tag, ?int $cityId = null): User
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
            $u->city_id = $cityId;
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

    /** @return array{business: User, customer: User, order_id: int, business_token: string, customer_token: string} */
    private function placeOrder(int $customerCityId, int $businessCityId, ?float $fixedFee = 30.0): array
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'RestQ', $businessCityId);
        if ($fixedFee !== null) {
            $business->delivery_fee_amount = $fixedFee;
            $business->save();
        }
        $section = MenuSection::query()->create(['business_id' => $business->id, 'name_ar' => 'الأطباق', 'is_active' => true, 'sort_order' => 1]);
        MenuItem::query()->create([
            'business_id' => $business->id, 'menu_section_id' => $section->id,
            'name_ar' => 'كشري', 'base_price' => 45, 'is_active' => true, 'sort_order' => 1,
        ]);

        $customer = $this->makeUser(User::TYPE_CLIENT, 'CustQ');
        $address = Address::query()->create([
            'user_id' => $customer->id, 'city_id' => $customerCityId, 'address_line' => 'شارع الاختبار', 'is_primary' => 1,
        ]);

        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $menu['sections'][0]['items'][0]['id'], 'qty' => 1,
        ])->assertSuccessful();

        $orderId = (int) $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', [
            'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ])->assertCreated()->json('data.order.id');

        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        return [
            'business' => $business, 'customer' => $customer, 'order_id' => $orderId,
            'business_token' => $businessToken, 'customer_token' => $customerToken,
        ];
    }

    private function courier(string $tag = 'Courier'): string
    {
        $user = $this->makeUser(User::TYPE_CLIENT, $tag);
        $token = $this->tokenFor($user);
        $this->carrierActing($token)->postJson('/api/v2/delivery/register')->assertCreated();

        return $token;
    }

    public function test_an_in_city_order_keeps_the_fixed_fee(): void
    {
        $o = $this->placeOrder($this->cityA, $this->cityA);

        $order = Order::find($o['order_id']);
        $this->assertSame(30.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_fee_status);
    }

    public function test_the_business_page_exposes_its_city(): void
    {
        $o = $this->placeOrder($this->cityA, $this->cityA);

        $page = $this->getJson('/api/v2/businesses/' . $o['business']->id)->assertOk();
        $this->assertSame($this->cityA, (int) $page->json('data.fulfillment.city_id'));
    }

    public function test_an_out_of_city_order_starts_unpriced_and_awaiting_a_quote(): void
    {
        $o = $this->placeOrder($this->cityB, $this->cityA);

        $order = Order::find($o['order_id']);
        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertSame(Order::FEE_AWAITING_QUOTE, $order->delivery_fee_status);
    }

    public function test_the_courier_must_write_a_fee_to_take_an_out_of_city_order(): void
    {
        $o = $this->placeOrder($this->cityB, $this->cityA);
        $courier = $this->courier();

        $this->actingWithToken($courier)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept')->assertStatus(422);

        $available = collect($this->actingWithToken($courier)->getJson('/api/v2/delivery/available-orders')->json('data.orders'))
            ->firstWhere('order_id', $o['order_id']);
        $this->assertTrue($available['needs_quote']);

        $this->actingWithToken($courier)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept', ['fee_amount' => 120])->assertCreated();

        $order = Order::find($o['order_id']);
        $this->assertSame(Order::FEE_PROPOSED, $order->delivery_fee_status);
        $this->assertSame(120.0, (float) $order->delivery_fee_proposed);
        $this->assertSame(0.0, (float) $order->delivery_fee, 'Nothing is on the invoice until the customer agrees.');
    }

    public function test_the_pickup_waits_until_the_customer_accepts_the_fee(): void
    {
        $o = $this->placeOrder($this->cityB, $this->cityA);
        $courier = $this->courier();
        $this->actingWithToken($courier)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept', ['fee_amount' => 120])->assertCreated();

        $this->actingWithToken($o['business_token'])->postJson('/api/v2/business/orders/' . $o['order_id'] . '/pickup-token')->assertStatus(422);

        $this->actingWithToken($o['customer_token'])->postJson('/api/v2/orders/' . $o['order_id'] . '/delivery-fee/accept')->assertOk()
            ->assertJsonPath('data.delivery_fee', 120)->assertJsonPath('data.delivery_fee_status', 'accepted');

        $order = Order::find($o['order_id']);
        $this->assertSame(120.0, (float) $order->delivery_fee);
        $this->assertGreaterThanOrEqual(45.0 + 120.0 - 0.01, (float) $order->final_total);

        $this->actingWithToken($o['business_token'])->postJson('/api/v2/business/orders/' . $o['order_id'] . '/pickup-token')->assertOk();
    }

    public function test_declining_releases_the_courier_and_reoffers_the_order(): void
    {
        $o = $this->placeOrder($this->cityB, $this->cityA);
        $first = $this->courier('First');
        $this->actingWithToken($first)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept', ['fee_amount' => 500])->assertCreated();

        $this->actingWithToken($o['customer_token'])->postJson('/api/v2/orders/' . $o['order_id'] . '/delivery-fee/decline')->assertOk()
            ->assertJsonPath('data.delivery_fee_status', 'awaiting_quote');

        $order = Order::find($o['order_id']);
        $this->assertNull($order->delivery_driver_id);
        $this->assertNull($order->delivery_fee_proposed);

        $second = $this->courier('Second');
        $ids = collect($this->actingWithToken($second)->getJson('/api/v2/delivery/available-orders')->json('data.orders'))->pluck('order_id')->all();
        $this->assertContains($o['order_id'], $ids);
        $this->actingWithToken($second)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept', ['fee_amount' => 150])->assertCreated();
        $this->assertSame(150.0, (float) Order::find($o['order_id'])->delivery_fee_proposed);
    }

    public function test_the_merchant_can_recommend_the_fee_and_a_stranger_cannot_answer_for_the_customer(): void
    {
        $o = $this->placeOrder($this->cityB, $this->cityA);
        $courier = $this->courier();
        $this->actingWithToken($courier)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept', ['fee_amount' => 400])->assertCreated();

        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/delivery-fee/recommendation', [
                'recommendation' => 'not_suitable', 'note' => 'المسافة أقل من كده',
            ])->assertOk();

        $order = $this->actingWithToken($o['customer_token'])->getJson('/api/v2/orders/' . $o['order_id'])->assertOk();
        $this->assertSame('not_suitable', $order->json('data.delivery_fee_quote.recommendation'));
        $this->assertSame(400.0, (float) $order->json('data.delivery_fee_quote.proposed_amount'));

        $stranger = $this->makeUser(User::TYPE_CLIENT, 'StrangerQ');
        $this->actingWithToken($this->tokenFor($stranger))->postJson('/api/v2/orders/' . $o['order_id'] . '/delivery-fee/accept')->assertStatus(404);

        $otherBusiness = $this->makeUser(User::TYPE_BUSINESS, 'OtherBiz', $this->cityA);
        $this->actingWithToken($this->tokenFor($otherBusiness))
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/delivery-fee/recommendation', ['recommendation' => 'suitable'])
            ->assertStatus(404);
    }

    public function test_a_business_assigned_courier_proposes_through_the_fee_endpoint(): void
    {
        $o = $this->placeOrder($this->cityB, $this->cityA);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'OwnRider');
        $riderToken = $this->tokenFor($rider);
        $driver = DeliveryDriver::query()->create(['user_id' => $rider->id, 'business_id' => $o['business']->id, 'is_active' => true]);

        $this->actingWithToken($o['business_token'])->postJson('/api/v2/business/orders/' . $o['order_id'] . '/assign-driver', ['driver_id' => $driver->id])->assertOk();
        $this->assertSame(Order::FEE_AWAITING_QUOTE, Order::find($o['order_id'])->delivery_fee_status);

        $this->actingWithToken($riderToken)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/fee-proposal', ['amount' => 90])->assertOk()
            ->assertJsonPath('data.delivery_fee_status', 'proposed');
    }
}
