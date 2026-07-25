<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Order it again": one tap re-adds a past order's lines to the cart, at
 * today's prices/availability (never a blind re-charge). A line whose offering
 * is gone is skipped and reported.
 */
class OrderReorderTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;
    private MenuItem $menuItem;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $users = User::query()->orderBy('id')->limit(2)->get();
        if ($users->count() < 2) {
            $this->markTestSkipped('Needs two users.');
        }
        [$this->client, $this->business] = [$users[0], $users[1]];

        $menuId = DB::table('menu_items')->insertGetId([
            'business_id' => $this->business->id,
            'name_ar' => 'صنف إعادة الطلب',
            'base_price' => 25,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->menuItem = MenuItem::findOrFail($menuId);

        // Any existing cart between this pair would blur the assertions.
        Order::query()
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->business->id)
            ->where('status', 'cart')
            ->delete();

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'status' => 'completed',
            'total' => 50,
            'address' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->order = Order::findOrFail($orderId);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'offering_type' => MenuItem::class,
            'offering_id' => $menuId,
            'menu_id' => $menuId,
            'qty' => 2,
            'price' => 25,
            'total_price' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reorder_adds_the_past_order_lines_back_to_the_cart(): void
    {
        Sanctum::actingAs($this->client);

        $this->postJson("/api/v2/orders/{$this->order->id}/reorder")
            ->assertCreated()
            ->assertJsonPath('data.added', 1)
            ->assertJsonPath('data.business_id', (int) $this->business->id);

        // A cart now exists for this business with the reordered line.
        $cart = Order::query()
            ->where('user_id', $this->client->id)
            ->where('business_id', $this->business->id)
            ->where('status', 'cart')
            ->with('items')
            ->firstOrFail();

        $line = $cart->items->firstWhere('offering_id', (int) $this->menuItem->id);
        $this->assertNotNull($line);
        $this->assertSame(2, (int) $line->qty);
    }

    public function test_only_the_customer_who_placed_it_can_reorder(): void
    {
        Sanctum::actingAs($this->business); // the seller, not the buyer

        $this->postJson("/api/v2/orders/{$this->order->id}/reorder")->assertNotFound();
    }

    public function test_a_gone_offering_is_skipped_not_fatal(): void
    {
        $this->menuItem->update(['is_active' => 0]); // retired since the order

        Sanctum::actingAs($this->client);

        $this->postJson("/api/v2/orders/{$this->order->id}/reorder")
            ->assertCreated()
            ->assertJsonPath('data.added', 0)
            ->assertJsonPath('data.skipped.0', (int) $this->menuItem->id);
    }
}
