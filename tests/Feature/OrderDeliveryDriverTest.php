<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\PromotesCarriers;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Who is bringing the order: the customer sees the assigned driver's name,
 * phone and vehicle on the order — and nothing before one is assigned.
 */
class OrderDeliveryDriverTest extends TestCase
{
    use DatabaseTransactions;

    use PromotesCarriers;
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
            'governorate_id' => (int) DB::table('governorates')->orderBy('id')->value('id'),
        ])->assertCreated()->json('data.order');

        $orderId = (int) $order['id'];
        $businessToken = $this->tokenFor($business);
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/accept')->assertSuccessful();
        $this->actingWithToken($businessToken)->postJson('/api/v2/business/orders/' . $orderId . '/preparing')->assertSuccessful();

        $driver = $this->makeUser(User::TYPE_CLIENT, 'Rider');
        $driverToken = $this->tokenFor($driver);
        $this->carrierActing($driverToken)->postJson('/api/v2/delivery/register')->assertCreated();
        $this->carrierActing($driverToken)->postJson('/api/v2/delivery/orders/' . $orderId . '/accept')->assertCreated();

        return ['order_id' => $orderId, 'customer' => $customer, 'driver_token' => $driverToken];
    }

    public function test_the_customer_sees_the_assigned_driver_on_the_order(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        ['order_id' => $orderId, 'customer' => $customer] = $this->assignedOrder($business);

        $driver = $this->actingWithToken($this->tokenFor($customer))
            ->getJson('/api/v2/orders/' . $orderId)->assertOk()->json('data.delivery_driver');

        $this->assertNotNull($driver);
        $this->assertStringStartsWith('Rider', $driver['name']);
        $this->assertNotEmpty($driver['phone']);
    }

    public function test_the_driver_is_null_before_anyone_is_assigned(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        ['order_id' => $orderId, 'customer' => $customer] = $this->assignedOrder($business);
        Order::query()->whereKey($orderId)->update(['delivery_driver_id' => null]);

        $this->actingWithToken($this->tokenFor($customer))
            ->getJson('/api/v2/orders/' . $orderId)->assertOk()->assertJsonPath('data.delivery_driver', null);
    }
}
