<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «جارٍ تجهيزه من فلان»: with several people receiving a business's orders, each order records who accepted it and who is
 * preparing it. The first to press «بدء التجهيز» owns it; the customer never sees staff names.
 */
class OrderHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $this->customer = User::query()->where('id', '!=', $this->business->id)->where('type', 'client')->orderBy('id')->firstOrFail();
        Wallet::query()->updateOrCreate(['user_id' => $this->business->id], ['balance' => 500, 'locked_balance' => 0, 'total_in' => 500, 'total_out' => 0, 'status' => 'active']);
    }

    private function receiver(string $name): User
    {
        $u = new User();
        $u->name = $name . ' ' . Str::random(4);
        $u->email = strtolower($name) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_CLIENT;
        $u->api_token = Str::random(80);
        $u->save();

        BusinessStaff::create([
            'business_id' => $this->business->id, 'user_id' => $u->id, 'title' => 'استقبال', 'status' => BusinessStaff::STATUS_ACCEPTED,
            'is_active' => true, 'capabilities' => [BusinessCapability::ORDERS],
        ]);

        return $u;
    }

    private function order(): Order
    {
        return Order::create([
            'user_id' => $this->customer->id, 'business_id' => $this->business->id,
            'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'status' => 'pending',
            'total' => 50, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 50, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    private function as(User $who)
    {
        Sanctum::actingAs($who);

        return $this->withHeader('X-Business-Id', (string) $this->business->id);
    }

    public function test_each_order_records_who_accepted_it_and_who_is_preparing_it(): void
    {
        $first = $this->receiver('Hassan');
        $second = $this->receiver('Mona');
        $mine = $this->order();
        $hers = $this->order();

        // two receivers take one order each
        $this->as($first)->postJson("/api/v2/business/orders/{$mine->id}/accept")->assertOk()
            ->assertJsonPath('data.handled_by.accepted_by.id', $first->id)->assertJsonPath('data.handled_by.preparing_by', null);
        $this->as($second)->postJson("/api/v2/business/orders/{$hers->id}/accept")->assertOk()
            ->assertJsonPath('data.handled_by.accepted_by.id', $second->id);

        // the first to start preparing owns it — here a different person than the one who accepted
        $this->as($second)->postJson("/api/v2/business/orders/{$mine->id}/preparing")->assertOk()
            ->assertJsonPath('data.handled_by.accepted_by.id', $first->id)->assertJsonPath('data.handled_by.preparing_by.id', $second->id);

        // a second press on the same order does not steal it — and says why
        $this->as($first)->postJson("/api/v2/business/orders/{$mine->id}/preparing")->assertStatus(409)
            ->assertJsonFragment(['message' => 'A colleague is already working on this order.']);
        $this->as($first)->postJson("/api/v2/business/orders/{$mine->id}/accept")->assertStatus(409);
        $this->assertSame($second->id, (int) $mine->refresh()->prepared_by);

        // marking it ready keeps the preparer
        $this->as($first)->postJson("/api/v2/business/orders/{$mine->id}/ready")->assertOk()
            ->assertJsonPath('data.handled_by.preparing_by.id', $second->id);

        // the business's list carries it for every order
        $list = collect($this->as($first)->getJson('/api/v2/business/orders')->assertOk()->json('data.orders.data') ?? $this->as($first)->getJson('/api/v2/business/orders')->json('data'));
        $row = $list->firstWhere('id', $hers->id);
        $this->assertNotNull($row);
        $this->assertSame($second->id, $row['handled_by']['accepted_by']['id']);
    }

    public function test_the_customer_never_sees_who_handled_the_order(): void
    {
        $staff = $this->receiver('Hassan');
        $order = $this->order();
        $this->as($staff)->postJson("/api/v2/business/orders/{$order->id}/accept")->assertOk();

        Sanctum::actingAs($this->customer);
        $res = $this->withHeader('X-Business-Id', (string) $this->customer->id)->getJson("/api/v2/orders/{$order->id}")->assertOk();
        $this->assertArrayNotHasKey('handled_by', $res->json('data'));
    }
}
