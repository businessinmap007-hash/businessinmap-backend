<?php

namespace Tests\Feature;

use App\Models\BusinessTable;
use App\Models\TableServiceCall;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The owner-panel board for dine-in table service calls (BIM-13.3): the pending
 * queue and resolving a call. Session-authenticated (behind business.panel).
 */
class BusinessTableCallPanelTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->firstOrFail();
        $this->client = User::query()->where('type', '!=', 'business')->firstOrFail();
    }

    private function pendingCall(string $label = '6', string $type = 'waiter'): TableServiceCall
    {
        $table = BusinessTable::create([
            'business_id' => $this->business->id,
            'label' => $label,
            'token' => BusinessTable::newToken(),
            'is_active' => true,
        ]);

        return TableServiceCall::create([
            'business_id' => $this->business->id,
            'business_table_id' => $table->id,
            'user_id' => $this->client->id,
            'type' => $type,
            'status' => TableServiceCall::STATUS_PENDING,
        ]);
    }

    public function test_the_panel_lists_a_pending_call(): void
    {
        $call = $this->pendingCall('6');

        $this->actingAs($this->business)
            ->get('/business/table-calls')
            ->assertOk()
            ->assertSee('طاولة 6')
            ->assertSee('نداء الطاقم');
    }

    public function test_the_owner_resolves_a_call(): void
    {
        $call = $this->pendingCall('4', 'bill');

        $this->actingAs($this->business)
            ->post("/business/table-calls/{$call->id}/resolve")
            ->assertRedirect();

        $this->assertDatabaseHas('table_service_calls', [
            'id' => $call->id, 'status' => 'resolved', 'resolved_by' => $this->business->id,
        ]);
    }

    public function test_a_client_cannot_reach_the_panel(): void
    {
        $this->actingAs($this->client)
            ->get('/business/table-calls')
            ->assertRedirect();
    }
}
