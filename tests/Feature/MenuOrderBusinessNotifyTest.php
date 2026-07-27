<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessTable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * When a customer places a menu order the owning business must be alerted with a
 * `menu_order_created` notification — and for a dine-in table scan that alert
 * carries the table label so staff know which table to serve.
 */
class MenuOrderBusinessNotifyTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $biz;
    private User $customer;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->firstOrFail();
        $this->itemId = $this->seedMenuItem($this->biz->id, null, 50.0, 'برجر')->id;
    }

    private function newOrderNotesFor(int $businessId): \Illuminate\Database\Eloquent\Builder
    {
        return AppNotification::query()
            ->where('user_id', $businessId)
            ->where('source_type', 'menu_order_created');
    }

    public function test_a_personal_checkout_notifies_the_business(): void
    {
        $before = $this->newOrderNotesFor($this->biz->id)->count();

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])
            ->assertCreated();
        $res = $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])
            ->assertCreated();

        $orderId = (int) $res->json('data.order.id');

        $note = $this->newOrderNotesFor($this->biz->id)->latest('id')->first();
        $this->assertNotNull($note, 'the business should be alerted of the new order');
        $this->assertSame($this->customer->id, (int) $note->actor_id);
        $this->assertSame($orderId, (int) $note->notifiable_id);
        $this->assertSame(Order::class, (string) $note->notifiable_type);
        $this->assertNull($note->meta['business_table_id'] ?? null, 'a pickup order carries no table');
        $this->assertSame($before + 1, $this->newOrderNotesFor($this->biz->id)->count());
    }

    public function test_a_table_scan_checkout_notifies_the_business_with_the_table_label(): void
    {
        $table = BusinessTable::create([
            'business_id' => $this->biz->id,
            'label' => '8',
            'token' => BusinessTable::newToken(),
            'is_active' => true,
        ]);

        // Scan the table → opens/joins its dine-in shared cart, host = customer.
        Sanctum::actingAs($this->customer);
        $scan = $this->postJson("/api/v2/table/{$table->token}/scan")->assertCreated();
        $orderId = (int) $scan->json('data.order_id');
        $this->assertGreaterThan(0, $orderId, 'the scan returns the table cart id');

        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])
            ->assertCreated();

        $before = $this->newOrderNotesFor($this->biz->id)->count();

        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])
            ->assertCreated();

        $note = $this->newOrderNotesFor($this->biz->id)->latest('id')->first();
        $this->assertNotNull($note, 'the restaurant should be alerted of the table order');
        $this->assertSame($orderId, (int) $note->notifiable_id);
        $this->assertSame((int) $table->id, (int) ($note->meta['business_table_id'] ?? 0));
        $this->assertSame('8', (string) ($note->meta['table_label'] ?? ''));
        $this->assertStringContainsString('8', (string) $note->body_ar, 'the body names the table');
        $this->assertSame($before + 1, $this->newOrderNotesFor($this->biz->id)->count());
    }

    public function test_the_business_queue_exposes_the_table_label(): void
    {
        $table = BusinessTable::create([
            'business_id' => $this->biz->id,
            'label' => '12',
            'token' => BusinessTable::newToken(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->customer);
        $scan = $this->postJson("/api/v2/table/{$table->token}/scan")->assertCreated();
        $orderId = (int) $scan->json('data.order_id');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])
            ->assertCreated();
        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])->assertCreated();

        // The business reads its queue and sees which table the order belongs to.
        Sanctum::actingAs($this->biz);
        $res = $this->getJson('/api/v2/business/orders')->assertOk();

        $row = collect($res->json('data'))->firstWhere('id', $orderId);
        $this->assertNotNull($row, 'the placed table order appears in the business queue');
        $this->assertSame($table->id, (int) $row['business_table_id']);
        $this->assertSame('12', (string) $row['table_label']);
    }
}
