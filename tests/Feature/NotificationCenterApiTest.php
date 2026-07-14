<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Gap #1 coverage: the notification center's write/read surface beyond index —
 * unread-count, show, mark-read, mark-all-read, archive, and per-user isolation.
 * Runs against the dev DB inside a transaction (rolls back).
 */
class NotificationCenterApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->orderBy('id')->firstOrFail();
        $this->other = User::query()->where('id', '!=', $this->user->id)->orderBy('id')->firstOrFail();
    }

    private function makeNotification(int $userId, array $attributes = []): AppNotification
    {
        return AppNotification::create(array_merge([
            'user_id' => $userId,
            'type' => AppNotification::TYPE_SYSTEM,
            'channel' => AppNotification::CHANNEL_IN_APP,
            'priority' => AppNotification::PRIORITY_NORMAL,
            'title_ar' => 'إشعار اختبار',
            'title_en' => 'Test notification',
            'body_ar' => 'نص',
            'body_en' => 'Body',
            'status' => AppNotification::STATUS_UNREAD,
        ], $attributes));
    }

    public function test_unread_count_reflects_only_own_unread_visible(): void
    {
        $before = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/notifications/unread-count')
            ->assertOk()
            ->json('data.unread_count');

        $this->makeNotification($this->user->id);                                   // +1 for me
        $this->makeNotification($this->user->id, ['status' => AppNotification::STATUS_READ]); // read, no count
        $this->makeNotification($this->other->id);                                  // another user, no count

        $after = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/notifications/unread-count')
            ->assertOk()
            ->json('data.unread_count');

        $this->assertSame((int) $before + 1, (int) $after);
    }

    public function test_show_returns_own_notification(): void
    {
        $notif = $this->makeNotification($this->user->id);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v2/notifications/{$notif->id}")
            ->assertOk()
            ->assertJsonPath('data.notification.id', $notif->id);
    }

    public function test_show_of_foreign_notification_is_not_found(): void
    {
        $foreign = $this->makeNotification($this->other->id);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v2/notifications/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_mark_read_flips_status_and_sets_timestamp(): void
    {
        $notif = $this->makeNotification($this->user->id);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v2/notifications/{$notif->id}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.status', AppNotification::STATUS_READ);

        $notif->refresh();
        $this->assertSame(AppNotification::STATUS_READ, $notif->status);
        $this->assertNotNull($notif->read_at);
    }

    public function test_cannot_mark_read_foreign_notification(): void
    {
        $foreign = $this->makeNotification($this->other->id);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v2/notifications/{$foreign->id}/read")
            ->assertNotFound();

        $this->assertSame(AppNotification::STATUS_UNREAD, $foreign->fresh()->status);
    }

    public function test_mark_all_read_clears_only_own_unread(): void
    {
        $mine = $this->makeNotification($this->user->id);
        $foreign = $this->makeNotification($this->other->id);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(AppNotification::STATUS_READ, $mine->fresh()->status);
        $this->assertSame(AppNotification::STATUS_UNREAD, $foreign->fresh()->status);
    }

    public function test_archive_moves_notification_out_of_unread(): void
    {
        $notif = $this->makeNotification($this->user->id);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v2/notifications/{$notif->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.notification.status', AppNotification::STATUS_ARCHIVED);

        $notif->refresh();
        $this->assertSame(AppNotification::STATUS_ARCHIVED, $notif->status);
        $this->assertNotNull($notif->archived_at);
    }

    public function test_index_filters_by_status(): void
    {
        $unread = $this->makeNotification($this->user->id);
        $read = $this->makeNotification($this->user->id, [
            'status' => AppNotification::STATUS_READ,
            'read_at' => now(),
        ]);

        $ids = collect(
            $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v2/notifications?status=' . AppNotification::STATUS_UNREAD)
                ->assertOk()
                ->json('data.notifications.data')
        )->pluck('id')->all();

        $this->assertContains($unread->id, $ids);
        $this->assertNotContains($read->id, $ids);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v2/notifications')->assertUnauthorized();
        $this->getJson('/api/v2/notifications/unread-count')->assertUnauthorized();
    }
}
