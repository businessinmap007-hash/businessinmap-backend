<?php

namespace Tests\Feature;

use App\Models\BusinessOperatorSession;
use App\Models\User;
use App\Services\Notifications\RealtimeNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Operator presence write-path: a business marks itself online (and heartbeats)
 * so RealtimeNotificationService's gate opens, then goes offline. Proves the
 * endpoints and that presence actually flips hasActiveSession.
 */
class OperatorSessionApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_start_opens_an_online_session_and_opens_the_realtime_gate(): void
    {
        $realtime = app(RealtimeNotificationService::class);
        $this->assertFalse($realtime->hasActiveSession((int) $this->business->id), 'no session before start');

        Sanctum::actingAs($this->business);
        $res = $this->postJson('/api/v2/operator/session/start', ['screen' => 'orders', 'expected_minutes' => 10])
            ->assertCreated();

        $this->assertSame('online', $res->json('data.status'));
        $this->assertDatabaseHas('business_operator_sessions', [
            'id' => (int) $res->json('data.id'),
            'business_id' => $this->business->id,
            'user_id' => $this->business->id,
            'status' => 'online',
            'ended_at' => null,
        ]);

        $this->assertTrue($realtime->hasActiveSession((int) $this->business->id), 'the gate is open once online');
    }

    public function test_heartbeat_requires_a_live_session_then_extends_it(): void
    {
        Sanctum::actingAs($this->business);

        // No session yet → 409.
        $this->postJson('/api/v2/operator/session/heartbeat')->assertStatus(409);

        $this->postJson('/api/v2/operator/session/start', ['expected_minutes' => 5])->assertCreated();

        // Force the session near expiry, then heartbeat pushes it back out.
        BusinessOperatorSession::query()->where('business_id', $this->business->id)
            ->update(['expected_until' => Carbon::now()->addSeconds(5)]);

        $this->postJson('/api/v2/operator/session/heartbeat', ['expected_minutes' => 15])->assertOk();

        $session = BusinessOperatorSession::query()->where('business_id', $this->business->id)->latest('id')->first();
        $this->assertTrue($session->expected_until->greaterThan(Carbon::now()->addMinutes(10)), 'heartbeat extended the window');
    }

    public function test_end_closes_the_session_and_shuts_the_gate(): void
    {
        $realtime = app(RealtimeNotificationService::class);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/operator/session/start')->assertCreated();
        $this->assertTrue($realtime->hasActiveSession((int) $this->business->id));

        $this->postJson('/api/v2/operator/session/end')->assertOk()->assertJsonPath('data.closed', 1);

        $this->assertFalse($realtime->hasActiveSession((int) $this->business->id), 'the gate shuts after end');
        $this->assertDatabaseMissing('business_operator_sessions', [
            'business_id' => $this->business->id, 'ended_at' => null,
        ]);
    }

    public function test_show_reports_online_state(): void
    {
        Sanctum::actingAs($this->business);

        $this->getJson('/api/v2/operator/session')->assertOk()->assertJsonPath('data.online', false);

        $this->postJson('/api/v2/operator/session/start', ['screen' => 'delivery'])->assertCreated();

        $this->getJson('/api/v2/operator/session')->assertOk()
            ->assertJsonPath('data.online', true)
            ->assertJsonPath('data.session.screen', 'delivery');
    }

    public function test_presence_is_scoped_to_the_business(): void
    {
        $realtime = app(RealtimeNotificationService::class);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/operator/session/start')->assertCreated();

        $other = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first();
        if ($other) {
            $this->assertFalse($realtime->hasActiveSession((int) $other->id), "another business is not online");
        }
    }
}
