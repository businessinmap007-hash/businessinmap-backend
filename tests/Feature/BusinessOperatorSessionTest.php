<?php

namespace Tests\Feature;

use App\Models\BusinessOperatorSession;
use App\Models\NotificationChannelRule;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Notifications\RealtimeNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The operator-presence table backs RealtimeNotificationService's gate. The
 * table was missing (model with no migration), so every realtime-enabled
 * dispatch threw a swallowed QueryException. With the table in place the read
 * path works and dispatch no longer throws.
 */
class BusinessOperatorSessionTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_a_realtime_event_dispatches_without_throwing(): void
    {
        NotificationChannelRule::ensureDefaults();

        // menu_order_accepted is realtime-enabled — before the table existed this
        // call threw at the hasActiveSession query.
        $result = app(NotificationDispatcherService::class)
            ->dispatch('menu_order_accepted', (int) $this->business->id, ['body_ar' => 'اختبار', 'body_en' => 'test']);

        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('app_notifications', [
            'id' => $result['notification_id'],
            'user_id' => $this->business->id,
            'source_type' => 'menu_order_accepted',
        ]);
    }

    public function test_no_session_means_no_active_operator(): void
    {
        $this->assertFalse(
            app(RealtimeNotificationService::class)->hasActiveSession((int) $this->business->id)
        );
    }

    public function test_an_online_session_is_detected_and_a_closed_or_expired_one_is_not(): void
    {
        $realtime = app(RealtimeNotificationService::class);

        $session = BusinessOperatorSession::create([
            'business_id' => $this->business->id,
            'status' => BusinessOperatorSession::STATUS_ONLINE,
            'started_at' => now(),
            'expected_until' => now()->addHour(),
            'last_activity_at' => now(),
        ]);
        $this->assertTrue($realtime->hasActiveSession((int) $this->business->id), 'an online session is active');

        // Ended → not active.
        $session->update(['ended_at' => now()]);
        $this->assertFalse($realtime->hasActiveSession((int) $this->business->id), 'an ended session is not active');

        // Online again but expired window → not active.
        $session->update(['ended_at' => null, 'expected_until' => now()->subMinute()]);
        $this->assertFalse($realtime->hasActiveSession((int) $this->business->id), 'an expired session is not active');
    }
}
