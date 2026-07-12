<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * Shared (group) cart: a host opens a cart for sharing, friends join by token
 * and each adds attributed lines, and the host checks out one invoice.
 */
class SharedCartTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $host;
    private User $member;
    private User $outsider;
    private User $biz;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $customers = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->take(3)->get();
        if ($customers->count() < 3) {
            $this->markTestSkipped('Needs three non-business users.');
        }
        [$this->host, $this->member, $this->outsider] = [$customers[0], $customers[1], $customers[2]];
        $this->itemId = $this->seedMenuItem($this->biz->id, null, 50.0, 'برجر')->id;
    }

    private function shareAsHost(): int
    {
        Sanctum::actingAs($this->host);
        $res = $this->postJson("/api/v2/cart/{$this->biz->id}/share")->assertCreated();

        return (int) $res->json('data.order_id');
    }

    private function token(int $orderId): string
    {
        return (string) Order::query()->whereKey($orderId)->value('share_token');
    }

    public function test_share_is_idempotent(): void
    {
        Sanctum::actingAs($this->host);
        $t1 = $this->postJson("/api/v2/cart/{$this->biz->id}/share")->assertCreated()->json('data.share_token');
        $t2 = $this->postJson("/api/v2/cart/{$this->biz->id}/share")->assertCreated()->json('data.share_token');
        $this->assertSame($t1, $t2, 're-sharing keeps the same token');
    }

    public function test_join_with_bad_token_is_404(): void
    {
        Sanctum::actingAs($this->member);
        $this->postJson('/api/v2/cart/join/nonexistenttoken123')->assertNotFound();
    }

    public function test_members_add_attributed_lines_and_totals_sum(): void
    {
        $orderId = $this->shareAsHost();
        $token = $this->token($orderId);

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/cart/join/{$token}")->assertCreated();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 2])->assertCreated();

        $res = $this->getJson("/api/v2/cart/shared/{$orderId}")->assertOk();

        // Same item from two people = two distinct, attributed lines.
        $this->assertCount(2, $res->json('data.cart.items'));
        $this->assertSame(150.0, (float) $res->json('data.cart.totals.grand_total')); // 1*50 + 2*50

        $breakdown = collect($res->json('data.cart.participants'));
        $this->assertSame(50.0, (float) $breakdown->firstWhere('user_id', $this->host->id)['subtotal']);
        $this->assertSame(100.0, (float) $breakdown->firstWhere('user_id', $this->member->id)['subtotal']);
        $this->assertSame('host', $breakdown->firstWhere('user_id', $this->host->id)['role']);
    }

    public function test_non_participant_is_forbidden(): void
    {
        $orderId = $this->shareAsHost();

        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/v2/cart/shared/{$orderId}")->assertForbidden();
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId])->assertForbidden();
    }

    public function test_member_cannot_edit_another_persons_line(): void
    {
        $orderId = $this->shareAsHost();
        $token = $this->token($orderId);

        Sanctum::actingAs($this->host);
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();
        $hostLineId = (int) OrderItem::query()->where('order_id', $orderId)->where('added_by_user_id', $this->host->id)->value('id');

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/cart/join/{$token}")->assertCreated();
        // member may not delete the host's line
        $this->deleteJson("/api/v2/cart/shared/{$orderId}/items/{$hostLineId}")->assertForbidden();
        // but the host may
        Sanctum::actingAs($this->host);
        $this->deleteJson("/api/v2/cart/shared/{$orderId}/items/{$hostLineId}")->assertOk();
    }

    public function test_only_host_can_checkout(): void
    {
        $orderId = $this->shareAsHost();
        $token = $this->token($orderId);

        Sanctum::actingAs($this->host);
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/cart/join/{$token}")->assertCreated();
        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])->assertForbidden();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])->assertCreated();
        $this->assertSame('pending', Order::query()->whereKey($orderId)->value('status'));
    }

    public function test_member_leaving_removes_their_lines(): void
    {
        $orderId = $this->shareAsHost();
        $token = $this->token($orderId);

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/cart/join/{$token}")->assertCreated();
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 3])->assertCreated();
        $this->assertSame(1, OrderItem::query()->where('order_id', $orderId)->where('added_by_user_id', $this->member->id)->count());

        $this->postJson("/api/v2/cart/shared/{$orderId}/leave")->assertOk();

        $this->assertSame(0, OrderItem::query()->where('order_id', $orderId)->where('added_by_user_id', $this->member->id)->count());
        $this->assertDatabaseMissing('order_participants', ['order_id' => $orderId, 'user_id' => $this->member->id]);
    }

    public function test_host_cannot_leave(): void
    {
        $orderId = $this->shareAsHost();
        Sanctum::actingAs($this->host);
        $this->postJson("/api/v2/cart/shared/{$orderId}/leave")->assertStatus(422);
    }
}
