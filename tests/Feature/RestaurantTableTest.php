<?php

namespace Tests\Feature;

use App\Models\BusinessTable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * Restaurant-table QR (BIM-13.3): scanning a table's permanent token opens or
 * joins that table's dine-in shared cart; the owner manages tables + prints QRs.
 */
class RestaurantTableTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $biz;
    private User $host;
    private User $member;
    private BusinessTable $table;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $customers = User::query()->where('id', '!=', $this->biz->id)->where('type', '!=', 'business')->orderBy('id')->take(2)->get();
        if ($customers->count() < 2) {
            $this->markTestSkipped('Needs two non-business users.');
        }
        [$this->host, $this->member] = [$customers[0], $customers[1]];
        $this->table = BusinessTable::create([
            'business_id' => $this->biz->id,
            'label' => 'طاولة 7',
            'token' => BusinessTable::newToken(),
            'is_active' => 1,
        ]);
        $this->itemId = $this->seedMenuItem($this->biz->id, null, 50.0, 'برجر')->id;
    }

    public function test_first_scan_opens_a_dine_in_table_cart_as_host(): void
    {
        Sanctum::actingAs($this->host);
        $res = $this->postJson("/api/v2/table/{$this->table->token}/scan")->assertCreated();
        $orderId = (int) $res->json('data.order_id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'business_id' => $this->biz->id,
            'business_table_id' => $this->table->id,
            'is_shared' => 1,
            'status' => 'cart',
            'fulfillment_type' => 'dine_in',
            'user_id' => $this->host->id,
        ]);
        $this->assertDatabaseHas('order_participants', [
            'order_id' => $orderId, 'user_id' => $this->host->id, 'role' => 'host',
        ]);
        $this->assertNotEmpty($res->json('data.share_token'));
    }

    public function test_second_scan_joins_the_same_table_cart_as_member(): void
    {
        Sanctum::actingAs($this->host);
        $first = (int) $this->postJson("/api/v2/table/{$this->table->token}/scan")->json('data.order_id');

        Sanctum::actingAs($this->member);
        $second = (int) $this->postJson("/api/v2/table/{$this->table->token}/scan")->assertCreated()->json('data.order_id');

        $this->assertSame($first, $second, 'scanning the same table joins the one open cart');
        $this->assertDatabaseHas('order_participants', [
            'order_id' => $first, 'user_id' => $this->member->id, 'role' => 'member',
        ]);
    }

    public function test_scan_unknown_or_inactive_token_is_404(): void
    {
        Sanctum::actingAs($this->host);
        $this->postJson('/api/v2/table/nonexistent-token/scan')->assertNotFound();

        $this->table->update(['is_active' => 0]);
        $this->postJson("/api/v2/table/{$this->table->token}/scan")->assertNotFound();
    }

    public function test_scan_after_checkout_opens_a_fresh_cart(): void
    {
        Sanctum::actingAs($this->host);
        $orderId = (int) $this->postJson("/api/v2/table/{$this->table->token}/scan")->json('data.order_id');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();
        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])->assertCreated();

        // The table's cart is now placed; a new scan opens a fresh one.
        $fresh = (int) $this->postJson("/api/v2/table/{$this->table->token}/scan")->assertCreated()->json('data.order_id');
        $this->assertNotSame($orderId, $fresh);
        $this->assertSame('pending', Order::query()->whereKey($orderId)->value('status'));
        $this->assertSame('cart', Order::query()->whereKey($fresh)->value('status'));
    }

    public function test_table_qr_endpoint_returns_svg(): void
    {
        $res = $this->get("/t/{$this->table->token}/qr");
        $res->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $res->getContent());
    }

    public function test_table_landing_page_renders_public(): void
    {
        $res = $this->get("/t/{$this->table->token}");
        $res->assertOk();
        $res->assertViewIs('cart.table');
        $res->assertSee('/api/v2/table/', false);
    }

    public function test_owner_can_create_and_delete_a_table(): void
    {
        $this->actingAs($this->biz);

        $this->post(route('business.tables.store', [], false), ['label' => 'الشرفة'])
            ->assertRedirect();
        $this->assertDatabaseHas('business_tables', ['business_id' => $this->biz->id, 'label' => 'الشرفة']);

        $row = BusinessTable::where('business_id', $this->biz->id)->where('label', 'الشرفة')->firstOrFail();
        $this->assertNotEmpty($row->token);

        $this->delete(route('business.tables.destroy', ['id' => $row->id], false))->assertRedirect();
        $this->assertDatabaseMissing('business_tables', ['id' => $row->id]);
    }

    public function test_owner_cannot_modify_another_businesss_table(): void
    {
        $other = User::query()->where('type', 'business')->where('id', '!=', $this->biz->id)->firstOrFail();
        $foreign = BusinessTable::create([
            'business_id' => $other->id, 'label' => 'طاولة غريبة', 'token' => BusinessTable::newToken(), 'is_active' => 1,
        ]);

        $this->actingAs($this->biz);
        $this->delete(route('business.tables.destroy', ['id' => $foreign->id], false))->assertNotFound();
        $this->assertDatabaseHas('business_tables', ['id' => $foreign->id]);
    }
}
