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
 * Order-status alerts (accepted/preparing/ready) must reach EVERY participant of
 * a shared/table order — not just the host who owns order.user_id — so a friend
 * who joined the table cart still learns the food is ready.
 */
class MenuOrderStatusAudienceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $biz;
    private User $host;
    private User $member;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $others = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->take(2)->get();
        if ($others->count() < 2) {
            $this->markTestSkipped('Needs two non-business users.');
        }
        [$this->host, $this->member] = [$others[0], $others[1]];
        $this->itemId = $this->seedMenuItem($this->biz->id, null, 50.0, 'برجر')->id;
    }

    private function acceptedNotesFor(int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return AppNotification::query()
            ->where('user_id', $userId)
            ->where('source_type', 'menu_order_accepted');
    }

    public function test_every_table_participant_hears_the_status_update(): void
    {
        $table = BusinessTable::create([
            'business_id' => $this->biz->id,
            'label' => '8',
            'token' => BusinessTable::newToken(),
            'is_active' => true,
        ]);

        // Host opens the table cart; member scans and joins.
        Sanctum::actingAs($this->host);
        $orderId = (int) $this->postJson("/api/v2/table/{$table->token}/scan")->assertCreated()->json('data.order_id');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])
            ->assertCreated();

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/table/{$table->token}/scan")->assertCreated();

        // Host places the shared order.
        Sanctum::actingAs($this->host);
        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])->assertCreated();

        $order = Order::query()->findOrFail($orderId);
        $this->assertTrue((bool) $order->is_shared, 'the placed order is still shared');

        $hostBefore = $this->acceptedNotesFor($this->host->id)->count();
        $memberBefore = $this->acceptedNotesFor($this->member->id)->count();

        // Business accepts → both the host AND the joined member are told.
        Sanctum::actingAs($this->biz);
        $this->postJson("/api/v2/business/orders/{$orderId}/accept")->assertOk();

        $this->assertSame($hostBefore + 1, $this->acceptedNotesFor($this->host->id)->count(), 'the host is notified');
        $this->assertSame($memberBefore + 1, $this->acceptedNotesFor($this->member->id)->count(), 'the joined member is notified');

        foreach ([$this->host->id, $this->member->id] as $uid) {
            $note = $this->acceptedNotesFor($uid)->latest('id')->first();
            $this->assertSame($orderId, (int) $note->notifiable_id);
        }
    }

    public function test_a_personal_order_still_notifies_only_the_owner(): void
    {
        Sanctum::actingAs($this->host);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();
        $orderId = (int) $this->postJson("/api/v2/cart/{$this->biz->id}/checkout", ['fulfillment_type' => 'pickup'])
            ->assertCreated()->json('data.order.id');

        $memberBefore = $this->acceptedNotesFor($this->member->id)->count();

        Sanctum::actingAs($this->biz);
        $this->postJson("/api/v2/business/orders/{$orderId}/accept")->assertOk();

        $this->assertSame(1, $this->acceptedNotesFor($this->host->id)
            ->where('notifiable_id', $orderId)->count(), 'the owner is notified once');
        $this->assertSame($memberBefore, $this->acceptedNotesFor($this->member->id)->count(), 'an unrelated user is not notified');
    }
}
