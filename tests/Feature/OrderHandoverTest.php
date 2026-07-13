<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Order-handover confirmation QR (BIM-13.5): a ready order issues a one-time
 * token; the other party scans it to confirm, flipping the order to completed
 * and consuming the token.
 */
class OrderHandoverTest extends TestCase
{
    use DatabaseTransactions;

    private User $biz;
    private User $customer;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $customers = User::query()->where('id', '!=', $this->biz->id)->where('type', '!=', 'business')->orderBy('id')->take(2)->get();
        if ($customers->count() < 2) {
            $this->markTestSkipped('Needs two non-business users.');
        }
        [$this->customer, $this->outsider] = [$customers[0], $customers[1]];
    }

    private function pendingOrder(): Order
    {
        return Order::create([
            'user_id' => $this->customer->id,
            'business_id' => $this->biz->id,
            'booking_id' => null,
            'fulfillment_type' => 'pickup',
            'status' => 'pending',
            'total' => 0, 'discount' => 0, 'delivery_fee' => 0, 'final_total' => 0,
            'payment_method' => 'cash', 'address' => '',
        ]);
    }

    public function test_party_can_issue_a_handover_token_for_a_ready_order(): void
    {
        $order = $this->pendingOrder();

        Sanctum::actingAs($this->biz);
        $res = $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->assertOk();

        $token = $res->json('data.handover_token');
        $this->assertNotEmpty($token);
        $this->assertSame('/h/' . $token, $res->json('data.scan_path'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'handover_token' => $token]);
    }

    public function test_issue_is_idempotent(): void
    {
        $order = $this->pendingOrder();
        Sanctum::actingAs($this->biz);

        $t1 = $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->json('data.handover_token');
        $t2 = $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->json('data.handover_token');
        $this->assertSame($t1, $t2);
    }

    public function test_outsider_cannot_issue(): void
    {
        $order = $this->pendingOrder();
        Sanctum::actingAs($this->outsider);
        $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->assertForbidden();
    }

    public function test_cannot_issue_for_a_non_ready_order(): void
    {
        $order = $this->pendingOrder();
        $order->update(['status' => 'cart']);

        Sanctum::actingAs($this->biz);
        $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->assertStatus(422);
    }

    public function test_scanning_party_confirms_handover_and_consumes_the_token(): void
    {
        $order = $this->pendingOrder();

        Sanctum::actingAs($this->biz);
        $token = $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->json('data.handover_token');

        // The customer scans the QR and confirms receipt.
        Sanctum::actingAs($this->customer);
        $res = $this->postJson("/api/v2/handover/{$token}/confirm")->assertOk();
        $this->assertSame('completed', $res->json('data.status'));

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->handover_confirmed_at);
        $this->assertNull($order->handover_token, 'the token is consumed (one-use)');

        // A second scan with the same token fails (already consumed).
        $this->postJson("/api/v2/handover/{$token}/confirm")->assertNotFound();
    }

    public function test_outsider_cannot_confirm(): void
    {
        $order = $this->pendingOrder();
        Sanctum::actingAs($this->biz);
        $token = $this->postJson("/api/v2/orders/{$order->id}/handover/issue")->json('data.handover_token');

        Sanctum::actingAs($this->outsider);
        $this->postJson("/api/v2/handover/{$token}/confirm")->assertForbidden();
        $this->assertSame('pending', Order::query()->whereKey($order->id)->value('status'));
    }

    public function test_confirm_unknown_token_is_404(): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/handover/nonexistent-token/confirm')->assertNotFound();
    }

    public function test_handover_web_page_and_qr(): void
    {
        $this->get('/h/sometoken')->assertOk()->assertViewIs('cart.handover');

        $qr = $this->get('/h/sometoken/qr');
        $qr->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $qr->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $qr->getContent());
    }

    public function test_owner_order_page_shows_the_handover_qr(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($this->biz);
        $res = $this->get(route('business.orders.show', ['id' => $order->id], false))->assertOk();

        // The page issues + shows the handover QR for the ready order.
        $order->refresh();
        $this->assertNotEmpty($order->handover_token);
        $res->assertSee('/h/' . $order->handover_token . '/qr', false);
    }
}
