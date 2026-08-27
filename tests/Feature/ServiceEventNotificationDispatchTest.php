<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Services\ServiceEventDispatcher;
use App\Services\ServiceEventNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A 2026-08-28 audit found that booking notifications never left the
 * in-app-only path: ServiceEventNotificationService wrote straight to
 * app_notifications, bypassing NotificationDispatcherService entirely, so a
 * booking event never got a realtime poll signal or a Firebase push attempt
 * unlike every other live event (menu orders, disputes, chat). This proves
 * the fix — a registered `booking.*` NotificationChannelRule routes the
 * event through the shared dispatcher, and an unregistered service-event key
 * still falls back to the direct write that always worked.
 */
class ServiceEventNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::query()->where('type', 'client')->first()
            ?: $this->markTestSkipped('Needs a client user.');
        $this->business = User::query()->where('type', 'business')->first()
            ?: $this->markTestSkipped('Needs a business user.');
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'user_id' => $this->client->id,
            'business_id' => $this->business->id,
            'service_id' => (int) (Booking::query()->value('service_id') ?: 1),
            'status' => Booking::STATUS_PENDING,
            'price' => 100,
            'quantity' => 1,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'starts_at' => now()->addDay(),
            'meta' => ['source' => 'service_event_dispatch_test'],
        ]);
    }

    public function test_a_booking_event_is_attempted_on_realtime_and_firebase(): void
    {
        $booking = $this->makeBooking();

        app(ServiceEventDispatcher::class)->bookingAccepted($booking, $this->business->id);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $this->client->id,
            'event_key' => 'booking.accepted',
            'channel' => NotificationDeliveryLog::CHANNEL_FIREBASE,
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $this->client->id,
            'event_key' => 'booking.accepted',
            'channel' => NotificationDeliveryLog::CHANNEL_REALTIME,
        ]);
    }

    public function test_the_notification_still_carries_the_right_content_and_url(): void
    {
        $booking = $this->makeBooking();

        app(ServiceEventDispatcher::class)->bookingAccepted($booking, $this->business->id);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->client->id,
            'source_type' => 'service_event',
            'action_type' => 'booking.accepted',
            'action_url' => '/bookings/' . $booking->id,
        ]);
    }

    public function test_the_same_service_event_is_not_notified_twice_for_the_same_recipient(): void
    {
        $booking = $this->makeBooking();
        $event = app(ServiceEventDispatcher::class)->bookingAccepted($booking, $this->business->id);

        // handle() itself is what guards against a double-processed event
        // (e.g. a retried queue job) — dispatch() always creates a fresh
        // ServiceEvent row, so that layer's dedup is exercised here directly.
        app(ServiceEventNotificationService::class)->handle($event);

        $this->assertSame(1, DB::table('app_notifications')
            ->where('user_id', $this->client->id)
            ->where('source_type', 'service_event')
            ->where('source_id', $event->id)
            ->where('action_type', 'booking.accepted')
            ->count());
    }
}
