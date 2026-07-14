<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * #6 centralized authorization: the `business` middleware gates every
 * /business/* route (one place, not inline per controller), and OrderPolicy
 * governs order viewing. Rolls back.
 */
class AuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->client = User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();
    }

    /** @return array<string,string> business-only GET routes */
    public static function businessRoutes(): array
    {
        return [
            'orders queue' => ['/api/v2/business/orders'],
            'menu items' => ['/api/v2/business/menu/items'],
            'menu sections' => ['/api/v2/business/menu/sections'],
            'offers' => ['/api/v2/business/offers'],
            'boost packages' => ['/api/v2/business/offers/boost/packages'],
        ];
    }

    /** @dataProvider businessRoutes */
    public function test_client_is_blocked_from_business_routes(string $uri): void
    {
        $this->actingAs($this->client, 'sanctum')->getJson($uri)->assertForbidden();
    }

    /** @dataProvider businessRoutes */
    public function test_business_is_allowed_on_business_routes(string $uri): void
    {
        $this->actingAs($this->business, 'sanctum')->getJson($uri)->assertOk();
    }

    public function test_order_policy_allows_parties_and_denies_others(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => 'pending',
            'total' => 10, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 10, 'payment_method' => 'cash', 'address' => 'x',
        ]);

        $this->assertTrue(Gate::forUser($this->client)->allows('view', $order));
        $this->assertTrue(Gate::forUser($this->business)->allows('view', $order));

        $stranger = User::query()
            ->whereNotIn('id', [$this->client->id, $this->business->id])
            ->orderBy('id')->first();
        if ($stranger) {
            $this->assertFalse(Gate::forUser($stranger)->allows('view', $order));
        }
    }
}
