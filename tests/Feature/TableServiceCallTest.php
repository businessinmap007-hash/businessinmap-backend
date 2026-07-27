<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessTable;
use App\Models\NotificationChannelRule;
use App\Models\TableServiceCall;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dine-in table service calls (BIM-13.3): a customer at a table calls staff or
 * asks for the bill; the business sees the live queue and resolves it. Plus the
 * regression that prep-status alerts to the customer are no longer dropped.
 */
class TableServiceCallTest extends TestCase
{
    use DatabaseTransactions;

    private User $biz;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->firstOrFail();
    }

    private function table(string $label = '5'): BusinessTable
    {
        return BusinessTable::create([
            'business_id' => $this->biz->id,
            'label' => $label,
            'token' => BusinessTable::newToken(),
            'is_active' => true,
        ]);
    }

    private function businessCallNotes(): \Illuminate\Database\Eloquent\Builder
    {
        return AppNotification::query()
            ->where('user_id', $this->biz->id)
            ->where('source_type', 'table_service_requested');
    }

    public function test_a_customer_call_notifies_the_business_with_the_table_label(): void
    {
        $table = $this->table('5');
        $before = $this->businessCallNotes()->count();

        Sanctum::actingAs($this->customer);
        $res = $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'bill'])
            ->assertCreated();

        $callId = (int) $res->json('data.call_id');
        $this->assertDatabaseHas('table_service_calls', [
            'id' => $callId,
            'business_id' => $this->biz->id,
            'business_table_id' => $table->id,
            'type' => 'bill',
            'status' => 'pending',
        ]);

        $note = $this->businessCallNotes()->latest('id')->first();
        $this->assertNotNull($note, 'the business should be alerted of the call');
        $this->assertSame($this->customer->id, (int) $note->actor_id);
        $this->assertSame($callId, (int) ($note->meta['call_id'] ?? 0));
        $this->assertSame('5', (string) ($note->meta['table_label'] ?? ''));
        $this->assertSame('bill', (string) ($note->meta['type'] ?? ''));
        $this->assertStringContainsString('5', (string) $note->body_ar);
        $this->assertSame($before + 1, $this->businessCallNotes()->count());
    }

    public function test_repeated_calls_of_the_same_type_are_deduped(): void
    {
        $table = $this->table('7');
        $before = $this->businessCallNotes()->count();

        Sanctum::actingAs($this->customer);
        $first = $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'waiter'])->assertCreated();
        $second = $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'waiter'])->assertCreated();

        $this->assertSame((int) $first->json('data.call_id'), (int) $second->json('data.call_id'), 'the same open call is reused');
        $this->assertSame(1, TableServiceCall::query()
            ->where('business_table_id', $table->id)->where('type', 'waiter')->where('status', 'pending')->count());
        $this->assertSame($before + 1, $this->businessCallNotes()->count(), 'a duplicate tap does not re-notify staff');
    }

    public function test_a_different_type_opens_a_separate_call(): void
    {
        $table = $this->table('9');

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'waiter'])->assertCreated();
        $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'bill'])->assertCreated();

        $this->assertSame(2, TableServiceCall::query()
            ->where('business_table_id', $table->id)->where('status', 'pending')->count());
    }

    public function test_an_unknown_or_inactive_table_is_404(): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/table/nonexistenttoken/call', ['type' => 'waiter'])->assertNotFound();

        $inactive = $this->table('3');
        $inactive->update(['is_active' => false]);
        $this->postJson("/api/v2/table/{$inactive->token}/call", ['type' => 'waiter'])->assertNotFound();
    }

    public function test_the_business_lists_and_resolves_a_call(): void
    {
        $table = $this->table('11');

        Sanctum::actingAs($this->customer);
        $res = $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'assistance'])->assertCreated();
        $callId = (int) $res->json('data.call_id');

        Sanctum::actingAs($this->biz);
        $list = $this->getJson('/api/v2/business/table-calls')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $callId);
        $this->assertNotNull($row, 'the pending call appears in the business queue');
        $this->assertSame('11', (string) $row['table']['label']);

        $this->postJson("/api/v2/business/table-calls/{$callId}/resolve")->assertOk();

        $this->assertDatabaseHas('table_service_calls', [
            'id' => $callId, 'status' => 'resolved', 'resolved_by' => $this->biz->id,
        ]);

        // Resolved calls drop out of the pending queue.
        $after = $this->getJson('/api/v2/business/table-calls')->assertOk();
        $this->assertNull(collect($after->json('data'))->firstWhere('id', $callId));
    }

    public function test_another_business_cannot_resolve_someone_elses_call(): void
    {
        $table = $this->table('12');

        Sanctum::actingAs($this->customer);
        $callId = (int) $this->postJson("/api/v2/table/{$table->token}/call", ['type' => 'waiter'])
            ->assertCreated()->json('data.call_id');

        $other = new User();
        $other->name = 'Other Biz ' . Str::random(4);
        $other->email = 'other-' . uniqid() . '@example.test';
        $other->phone = '01' . random_int(100000000, 999999999);
        $other->password = 'secret-password';
        $other->type = User::TYPE_BUSINESS;
        $other->api_token = Str::random(80);
        $other->save();

        Sanctum::actingAs($other);
        $this->postJson("/api/v2/business/table-calls/{$callId}/resolve")->assertNotFound();

        $this->assertDatabaseHas('table_service_calls', ['id' => $callId, 'status' => 'pending']);
    }

    public function test_prep_status_events_are_registered_so_the_customer_is_not_dropped(): void
    {
        // Regression: menu_order_accepted/preparing/ready were dispatched by the
        // business transitions but never registered as channel rules, so the
        // dispatcher returned early ('rule_disabled_or_missing') and the customer
        // never learned their order's status. They must now exist and be active.
        NotificationChannelRule::ensureDefaults();

        foreach (['menu_order_accepted', 'menu_order_preparing', 'menu_order_ready', 'table_service_requested'] as $event) {
            $rule = NotificationChannelRule::query()->where('event_key', $event)->first();
            $this->assertNotNull($rule, "$event must be a registered channel rule");
            $this->assertTrue((bool) $rule->is_active, "$event must be active so the dispatcher delivers it");
        }
    }
}
