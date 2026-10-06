<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\NotificationChannelRule;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\NotificationCategories;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اعدادات الاشعارات ما يكون منها صامت وما يكون فعال بزر سويتش» — المالك، 2026-10-06. Rolls back.
 */
class NotificationPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();
        Sanctum::actingAs($this->me);
    }

    public function test_every_category_is_active_until_switched_off(): void
    {
        $rows = collect($this->getJson('/api/v2/me/notification-preferences')->assertOk()->json('data.categories'));

        $this->assertSame(array_keys(NotificationCategories::ALL), $rows->pluck('key')->all());
        $this->assertSame([true], $rows->pluck('enabled')->unique()->values()->all());
    }

    public function test_a_switch_is_saved_and_only_that_one_changes(): void
    {
        $this->putJson('/api/v2/me/notification-preferences', ['categories' => ['messages' => false, 'nonsense' => false]])->assertOk();

        $rows = collect($this->getJson('/api/v2/me/notification-preferences')->json('data.categories'))->pluck('enabled', 'key');
        $this->assertFalse($rows['messages']);
        $this->assertTrue($rows['orders']);
        $this->assertArrayNotHasKey('nonsense', $rows->all());

        $this->putJson('/api/v2/me/notification-preferences', ['categories' => ['messages' => true]])->assertOk();
        $this->assertTrue(collect($this->getJson('/api/v2/me/notification-preferences')->json('data.categories'))->firstWhere('key', 'messages')['enabled']);
    }

    public function test_a_silent_category_still_lands_in_the_inbox_but_raises_no_push(): void
    {
        $this->putJson('/api/v2/me/notification-preferences', ['categories' => ['messages' => false]])->assertOk();

        $result = app(NotificationDispatcherService::class)->dispatch('chat_message', (int) $this->me->id, ['body_ar' => 'مرحبا']);

        $this->assertTrue($result['created']);
        $this->assertTrue($result['silenced']);
        $this->assertTrue(AppNotification::query()->where('id', $result['notification_id'])->exists(), 'it is in the inbox');
        $this->assertSame('silenced_by_user', NotificationDeliveryLog::query()->where('notification_id', $result['notification_id'])->where('channel', NotificationDeliveryLog::CHANNEL_FIREBASE)->value('failed_reason'));
    }

    public function test_an_active_category_is_dispatched_as_before(): void
    {
        $result = app(NotificationDispatcherService::class)->dispatch('chat_message', (int) $this->me->id, ['body_ar' => 'مرحبا']);

        $this->assertTrue($result['created']);
        $this->assertArrayNotHasKey('silenced', $result);
    }

    public function test_a_critical_alert_and_the_platforms_own_announcement_are_never_silenced(): void
    {
        $this->putJson('/api/v2/me/notification-preferences', ['categories' => array_fill_keys(array_keys(NotificationCategories::ALL), false)])->assertOk();

        $this->assertFalse(NotificationCategories::isSilenced((int) $this->me->id, 'system_announcement'));
        $this->assertFalse(NotificationCategories::isSilenced((int) $this->me->id, 'wallet_deposit', critical: true));
        $this->assertTrue(NotificationCategories::isSilenced((int) $this->me->id, 'wallet_deposit'));
    }

    public function test_every_known_event_key_is_in_exactly_one_category_or_is_the_platforms_own(): void
    {
        NotificationChannelRule::ensureDefaults();
        $uncategorised = NotificationChannelRule::query()->pluck('event_key')->filter(fn ($key) => NotificationCategories::of($key) === null && ! in_array($key, NotificationCategories::LOCKED, true))->values()->all();

        $this->assertSame([], $uncategorised, 'a notification nobody can switch off by mistake: add its prefix to NotificationCategories');
    }
}
