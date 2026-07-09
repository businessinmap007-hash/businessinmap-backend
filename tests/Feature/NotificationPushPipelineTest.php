<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\NotificationChannelRule;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Notifications\FirebasePushService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Push pipeline wiring: FirebasePushService reads the live user_push_tokens
 * store, and the dispatcher fans a channel-rule event out to in-app + a firebase
 * delivery-log attempt. FCM itself is unconfigured in tests, so the push is
 * reported as skipped ('firebase_not_configured') rather than actually sent —
 * which still proves the token lookup + wiring. All rows rolled back.
 */
class NotificationPushPipelineTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::query()->first();

        if (! $user) {
            $this->markTestSkipped('Needs at least one user.');
        }

        $this->user = $user;
        UserPushToken::query()->where('user_id', $user->id)->delete();
    }

    private function makeNotification(): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => (int) $this->user->id,
            'type' => AppNotification::TYPE_GUARANTEE,
            'channel' => AppNotification::CHANNEL_IN_APP,
            'priority' => AppNotification::PRIORITY_NORMAL,
            'title_ar' => 'اختبار', 'title_en' => 'Test',
            'status' => AppNotification::STATUS_UNREAD,
        ]);
    }

    public function test_push_service_reads_the_live_user_push_tokens_table(): void
    {
        $notification = $this->makeNotification();
        $push = app(FirebasePushService::class);

        // No token → the lookup itself comes up empty.
        $this->assertSame('no_active_device_tokens', $push->sendToUser((int) $this->user->id, $notification)['reason'] ?? null);

        // With an active token in user_push_tokens the lookup succeeds and we
        // only fall through on missing FCM credentials — proving the repoint.
        UserPushToken::query()->create([
            'user_id' => (int) $this->user->id,
            'platform' => UserPushToken::PLATFORM_ANDROID,
            'provider' => 'fcm',
            'token' => 'test-token-' . uniqid(),
            'is_active' => 1,
            'last_seen_at' => now(),
        ]);

        $this->assertSame('firebase_not_configured', $push->sendToUser((int) $this->user->id, $notification)['reason'] ?? null);
    }

    public function test_dispatcher_creates_in_app_row_and_logs_a_firebase_attempt(): void
    {
        UserPushToken::query()->create([
            'user_id' => (int) $this->user->id,
            'platform' => UserPushToken::PLATFORM_ANDROID,
            'provider' => 'fcm',
            'token' => 'test-token-' . uniqid(),
            'is_active' => 1,
            'last_seen_at' => now(),
        ]);

        $result = app(NotificationDispatcherService::class)->dispatch('coguarantor_invited', (int) $this->user->id, [
            'title_ar' => 'دعوة ضمان', 'title_en' => 'Co-guarantor request',
            'source_id' => 999999,
        ]);

        $this->assertTrue((bool) ($result['created'] ?? false), 'dispatch should create a notification for an active rule');

        // In-app row landed in app_notifications.
        $this->assertTrue(
            AppNotification::query()->whereKey($result['notification_id'])->where('user_id', $this->user->id)->exists()
        );

        // The rule enables firebase → a delivery-log row is written for it.
        $this->assertTrue(
            NotificationDeliveryLog::query()
                ->where('notification_id', $result['notification_id'])
                ->where('channel', NotificationDeliveryLog::CHANNEL_FIREBASE)
                ->exists(),
            'a firebase delivery-log row should be recorded'
        );
    }
}
