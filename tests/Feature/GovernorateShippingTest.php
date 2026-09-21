<?php

namespace Tests\Feature;

use App\Models\Address;
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
 * A product order to another governorate is shipped, not couriered: the merchant
 * picks a Shipping & Delivery company (its fixed price lands on the invoice) and
 * the company sets the appointment and moves it to shipped / delivered.
 */
class GovernorateShippingTest extends TestCase
{
    use DatabaseTransactions;
    use PromotesCarriers;

    private int $govA;

    private int $govB;

    private int $govC;

    protected function setUp(): void
    {
        parent::setUp();

        $ids = DB::table('governorates')->orderBy('id')->limit(3)->pluck('id')->all();
        [$this->govA, $this->govB, $this->govC] = array_map('intval', $ids);
    }

    private function makeUser(string $type, string $tag, ?int $governorateId = null): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->governorate_id = $governorateId;

        if ($type === User::TYPE_BUSINESS) {
            $u->category_id = 16;
            $u->category_child_id = 245;
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
        return $this->postJson('/api/v2/auth/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk()->json('token');
    }

    private function carrier(string $tag, int $governorateId, array $rates = []): array
    {
        $user = $this->makeUser(User::TYPE_BUSINESS, $tag, $governorateId);
        $this->promoteToCarrier($user);
        $token = $this->tokenFor($user);

        if ($rates) {
            $this->actingWithToken($token)->putJson('/api/v2/business/shipping/rates', [
                'rates' => collect($rates)->map(fn ($price, $gov) => ['governorate_id' => $gov, 'price' => $price])->values()->all(),
            ])->assertOk();
        }

        return [$user, $token];
    }

    /** @return array{business: User, customer: User, order_id: int, business_token: string, customer_token: string} */
    private function shippingOrder(): array
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Shop', $this->govA);
        $section = MenuSection::query()->create(['business_id' => $business->id, 'name_ar' => 'منتجات', 'is_active' => true, 'sort_order' => 1]);
        MenuItem::query()->create([
            'business_id' => $business->id, 'menu_section_id' => $section->id,
            'name_ar' => 'قميص', 'base_price' => 200, 'is_active' => true, 'sort_order' => 1,
        ]);

        $customer = $this->makeUser(User::TYPE_CLIENT, 'ShipCust');
        $address = Address::query()->create([
            'user_id' => $customer->id, 'governorate_id' => $this->govB, 'address_line' => 'شارع الشحن', 'is_primary' => 1,
        ]);

        $customerToken = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data');
        $this->actingWithToken($customerToken)->postJson('/api/v2/cart/items', [
            'kind' => 'menu', 'offering_id' => $menu['sections'][0]['items'][0]['id'], 'qty' => 1,
        ])->assertSuccessful();
        $orderId = (int) $this->actingWithToken($customerToken)->postJson('/api/v2/cart/' . $business->id . '/checkout', [
            'fulfillment_type' => 'delivery', 'address_id' => $address->id, 'payment_method' => 'cash_on_delivery',
        ])->assertCreated()->json('data.order.id');

        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();

        return [
            'business' => $business, 'customer' => $customer, 'order_id' => $orderId,
            'business_token' => $businessToken, 'customer_token' => $customerToken,
        ];
    }

    public function test_an_order_to_another_governorate_is_a_shipping_order_with_no_fee_yet(): void
    {
        $o = $this->shippingOrder();

        $order = Order::find($o['order_id']);
        $this->assertSame(Order::SHIP_AWAITING_COMPANY, $order->shipping_status);
        $this->assertSame($this->govB, (int) $order->shipping_to_governorate_id);
        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_fee_status, 'Not the courier quote flow.');
    }

    public function test_a_shipping_order_is_never_offered_to_couriers(): void
    {
        $o = $this->shippingOrder();
        $this->actingWithToken($o['business_token'])->postJson('/api/v2/business/orders/' . $o['order_id'] . '/preparing')->assertSuccessful();

        $courier = $this->makeUser(User::TYPE_CLIENT, 'Courier');
        $token = $this->tokenFor($courier);
        $this->carrierActing($token)->postJson('/api/v2/delivery/register')->assertCreated();

        $ids = collect($this->actingWithToken($token)->getJson('/api/v2/delivery/available-orders')->json('data.orders'))->pluck('order_id')->all();
        $this->assertNotContains($o['order_id'], $ids);
        $this->actingWithToken($token)->postJson('/api/v2/delivery/orders/' . $o['order_id'] . '/accept')->assertStatus(409);
    }

    public function test_only_companies_that_ship_to_that_governorate_are_offered_with_their_price(): void
    {
        $o = $this->shippingOrder();
        [$cheap] = $this->carrier('CheapShip', $this->govA, [$this->govB => 60, $this->govC => 90]);
        [$other] = $this->carrier('NoRoute', $this->govA, [$this->govC => 50]);

        $companies = collect($this->actingWithToken($o['business_token'])
            ->getJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-companies')->assertOk()->json('data.companies'));

        $this->assertContains($cheap->id, $companies->pluck('id')->all());
        $this->assertNotContains($other->id, $companies->pluck('id')->all());
        $this->assertSame(60.0, (float) $companies->firstWhere('id', $cheap->id)['price']);
    }

    public function test_the_list_keeps_only_companies_running_the_route_today_or_tomorrow_from_the_business_governorate(): void
    {
        $o = $this->shippingOrder();

        $this->carrier('Today', $this->govA, [$this->govB => 70]);
        [$monday] = $this->carrier('MondayOnly', $this->govA, []);
        [$tuesday] = $this->carrier('TuesdayOnly', $this->govA, []);
        [$thursday] = $this->carrier('ThursdayOnly', $this->govA, []);
        [$elsewhere] = $this->carrier('OtherOrigin', $this->govC, []);

        foreach ([[$monday, [1]], [$tuesday, [2]], [$thursday, [4]], [$elsewhere, [1, 2]]] as [$company, $days]) {
            $this->actingWithToken($this->tokenFor($company))->putJson('/api/v2/business/shipping/rates', [
                'rates' => [['governorate_id' => $this->govB, 'price' => 50, 'days' => $days]],
            ])->assertOk();
        }

        // Monday: the business is in A, the customer in B.
        \Illuminate\Support\Carbon::setTestNow('2026-09-21 09:00:00');
        try {
            $ids = collect($this->actingWithToken($o['business_token'])
                ->getJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-companies')->assertOk()->json('data.companies'))
                ->pluck('id')->all();
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }

        $this->assertContains($monday->id, $ids, 'Runs Monday (today).');
        $this->assertContains($tuesday->id, $ids, 'Runs Tuesday (tomorrow).');
        $this->assertNotContains($thursday->id, $ids, 'Neither today nor tomorrow.');
        $this->assertNotContains($elsewhere->id, $ids, 'Ships from another governorate.');
    }

    public function test_picking_a_company_puts_its_price_on_the_invoice(): void
    {
        $o = $this->shippingOrder();
        [$company] = $this->carrier('ShipCo', $this->govA, [$this->govB => 60]);
        $before = (float) Order::find($o['order_id'])->final_total;

        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-company', ['company_id' => $company->id])
            ->assertOk()->assertJsonPath('data.shipping.status', 'awaiting_appointment');

        $order = Order::find($o['order_id']);
        $this->assertSame(60.0, (float) $order->shipping_fee);
        $this->assertSame(60.0, (float) $order->delivery_fee);
        $this->assertEqualsWithDelta($before + 60.0, (float) $order->final_total, 0.01);

        // Choosing again swaps the fee rather than stacking it.
        [$second] = $this->carrier('ShipCo2', $this->govA, [$this->govB => 45]);
        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-company', ['company_id' => $second->id])->assertOk();
        $this->assertEqualsWithDelta($before + 45.0, (float) Order::find($o['order_id'])->final_total, 0.01);
    }

    public function test_a_company_without_a_rate_for_that_governorate_is_refused(): void
    {
        $o = $this->shippingOrder();
        [$company] = $this->carrier('WrongRoute', $this->govA, [$this->govC => 70]);

        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-company', ['company_id' => $company->id])
            ->assertStatus(422);
    }

    public function test_the_company_sets_the_appointment_then_ships_and_delivers(): void
    {
        $o = $this->shippingOrder();
        [$company, $companyToken] = $this->carrier('RunCo', $this->govA, [$this->govB => 60]);
        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-company', ['company_id' => $company->id])->assertOk();

        $listed = collect($this->actingWithToken($companyToken)->getJson('/api/v2/business/shipping/orders')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($o['order_id'], $listed);

        $base = '/api/v2/business/shipping/orders/' . $o['order_id'];
        $this->actingWithToken($companyToken)->postJson($base . '/shipped')->assertStatus(409);
        $this->actingWithToken($companyToken)->postJson($base . '/appointment', ['appointment_at' => now()->subDay()->toIso8601String()])->assertStatus(422);

        $when = now()->addDays(2)->startOfHour();
        $this->actingWithToken($companyToken)->postJson($base . '/appointment', ['appointment_at' => $when->toIso8601String(), 'note' => 'الصباح'])
            ->assertOk()->assertJsonPath('data.shipping.status', 'scheduled');

        // The customer sees it on the order.
        $seen = $this->actingWithToken($o['customer_token'])->getJson('/api/v2/orders/' . $o['order_id'])->assertOk();
        $this->assertSame('scheduled', $seen->json('data.shipping.status'));
        $this->assertNotNull($seen->json('data.shipping.appointment_at'));

        // A rescheduled appointment replaces the old one.
        $later = $when->copy()->addDay();
        $this->actingWithToken($companyToken)->postJson($base . '/appointment', ['appointment_at' => $later->toIso8601String()])->assertOk();
        $this->assertTrue(Order::find($o['order_id'])->shipping_appointment_at->equalTo($later));

        $this->actingWithToken($companyToken)->postJson($base . '/shipped')->assertOk()->assertJsonPath('data.shipping.status', 'shipped');
        $this->actingWithToken($companyToken)->postJson($base . '/delivered')->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_only_the_chosen_company_can_run_the_order_and_only_a_shipping_account_keeps_rates(): void
    {
        $o = $this->shippingOrder();
        [$company] = $this->carrier('Chosen', $this->govA, [$this->govB => 60]);
        [, $strangerToken] = $this->carrier('Stranger', $this->govA, [$this->govB => 50]);
        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-company', ['company_id' => $company->id])->assertOk();

        $this->actingWithToken($strangerToken)
            ->postJson('/api/v2/business/shipping/orders/' . $o['order_id'] . '/appointment', ['appointment_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(404);

        $restaurant = $this->makeUser(User::TYPE_BUSINESS, 'JustAShop', $this->govA);
        $this->actingWithToken($this->tokenFor($restaurant))
            ->putJson('/api/v2/business/shipping/rates', ['rates' => [['governorate_id' => $this->govB, 'price' => 10]]])
            ->assertStatus(403);
    }

    public function test_an_ordinary_in_governorate_order_is_not_shipping(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'LocalShop', $this->govA);
        $section = MenuSection::query()->create(['business_id' => $business->id, 'name_ar' => 'م', 'is_active' => true, 'sort_order' => 1]);
        MenuItem::query()->create(['business_id' => $business->id, 'menu_section_id' => $section->id, 'name_ar' => 'ص', 'base_price' => 50, 'is_active' => true, 'sort_order' => 1]);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'LocalCust');
        $address = Address::query()->create(['user_id' => $customer->id, 'governorate_id' => $this->govA, 'address_line' => 'x', 'is_primary' => 1]);
        $token = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->json('data');
        $this->actingWithToken($token)->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $menu['sections'][0]['items'][0]['id'], 'qty' => 1])->assertSuccessful();
        $id = (int) $this->actingWithToken($token)->postJson('/api/v2/cart/' . $business->id . '/checkout', ['fulfillment_type' => 'delivery', 'address_id' => $address->id])->json('data.order.id');

        $this->assertNull(Order::find($id)->shipping_status);
    }

    public function test_the_customer_can_confirm_the_appointment_and_a_new_time_needs_a_fresh_yes(): void
    {
        $o = $this->shippingOrder();
        [$company, $companyToken] = $this->carrier('OkCo', $this->govA, [$this->govB => 60]);
        $this->actingWithToken($o['business_token'])
            ->postJson('/api/v2/business/orders/' . $o['order_id'] . '/shipping-company', ['company_id' => $company->id])->assertOk();

        $ok = '/api/v2/orders/' . $o['order_id'] . '/shipping/appointment-ok';
        $this->actingWithToken($o['customer_token'])->postJson($ok)->assertStatus(409); // nothing scheduled yet

        $base = '/api/v2/business/shipping/orders/' . $o['order_id'];
        $this->actingWithToken($companyToken)->postJson($base . '/appointment', ['appointment_at' => now()->addDays(2)->toIso8601String()])->assertOk();

        $this->actingWithToken($o['business_token'])->postJson($ok)->assertStatus(404); // only the customer
        $this->actingWithToken($o['customer_token'])->postJson($ok)->assertOk()
            ->assertJsonPath('data.shipping.appointment_confirmed_at', fn ($v) => $v !== null);

        $this->actingWithToken($companyToken)->postJson($base . '/appointment', ['appointment_at' => now()->addDays(3)->toIso8601String()])->assertOk();
        $this->assertNull(Order::find($o['order_id'])->shipping_appointment_confirmed_at);
    }

    public function test_a_delivery_order_must_say_where_and_registration_needs_a_location(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'NoAddrShop', $this->govA);
        $section = MenuSection::query()->create(['business_id' => $business->id, 'name_ar' => 'م', 'is_active' => true, 'sort_order' => 1]);
        MenuItem::query()->create(['business_id' => $business->id, 'menu_section_id' => $section->id, 'name_ar' => 'ص', 'base_price' => 50, 'is_active' => true, 'sort_order' => 1]);
        $customer = $this->makeUser(User::TYPE_CLIENT, 'NoAddrCust');
        $token = $this->tokenFor($customer);
        $menu = $this->getJson('/api/v2/discovery/menu/' . $business->id)->json('data');
        $this->actingWithToken($token)->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $menu['sections'][0]['items'][0]['id'], 'qty' => 1])->assertSuccessful();

        $this->actingWithToken($token)->postJson('/api/v2/cart/' . $business->id . '/checkout', ['fulfillment_type' => 'delivery', 'address' => 'شارع بلا محافظة'])
            ->assertStatus(422)->assertJsonValidationErrors('address_id');

        // A bare governorate is enough to route it (here: another governorate = shipping).
        $id = (int) $this->actingWithToken($token)->postJson('/api/v2/cart/' . $business->id . '/checkout', ['fulfillment_type' => 'delivery', 'governorate_id' => $this->govB])
            ->assertCreated()->json('data.order.id');
        $this->assertSame(Order::SHIP_AWAITING_COMPANY, Order::find($id)->shipping_status);

        $this->postJson('/api/v2/auth/register', [
            'name' => 'بلا موقع', 'email' => 'noloc@example.test', 'phone' => '01099911111',
            'password' => 'Test12345!', 'password_confirmation' => 'Test12345!', 'type' => 'client', 'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['governorate_id', 'city_id', 'address_line']);
    }
}
