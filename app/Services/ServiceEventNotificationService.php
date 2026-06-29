<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\ServiceEvent;
use App\Models\User;
use App\Notifications\ServiceEventDatabaseNotification;
use App\Services\Notifications\InAppNotificationService;
use App\Support\AdminV2\ServiceEvents\ServiceEventKeys;
use Illuminate\Support\Facades\Route;

class ServiceEventNotificationService
{
    public function __construct(
        protected InAppNotificationService $inAppNotificationService
    ) {
    }

    public function handle(ServiceEvent $event): void
    {
        match ((string) $event->event_key) {
            ServiceEventKeys::BOOKING_REQUESTED => $this->bookingRequested($event),
            ServiceEventKeys::BOOKING_ACCEPTED => $this->bookingAccepted($event),
            ServiceEventKeys::BOOKING_REJECTED => $this->bookingRejected($event),
            ServiceEventKeys::BOOKING_CANCELLED => $this->bookingCancelled($event),
            ServiceEventKeys::BOOKING_STARTED => $this->bookingStarted($event),
            ServiceEventKeys::BOOKING_COMPLETED => $this->bookingCompleted($event),
            ServiceEventKeys::BOOKING_CLIENT_CONFIRMED => $this->bookingClientConfirmed($event),
            ServiceEventKeys::BOOKING_BUSINESS_CONFIRMED => $this->bookingBusinessConfirmed($event),
            ServiceEventKeys::BOOKING_DEPOSIT_FROZEN => $this->bookingDepositFrozen($event),
            ServiceEventKeys::BOOKING_DEPOSIT_RELEASED => $this->bookingDepositReleased($event),
            ServiceEventKeys::BOOKING_DEPOSIT_REFUNDED => $this->bookingDepositRefunded($event),
            ServiceEventKeys::BOOKING_DISPUTE_OPENED => $this->bookingDisputeOpened($event),
            ServiceEventKeys::BOOKING_REMINDER_24H => $this->bookingReminder24h($event),
            ServiceEventKeys::BOOKING_REMINDER_1H => $this->bookingReminder1h($event),
            default => null,
        };
    }

    protected function bookingRequested(ServiceEvent $event): void
    {
        $booking = $this->booking($event);

        $this->notifyBusiness($event, 'طلب حجز جديد', 'لديك طلب حجز جديد يحتاج إلى مراجعة.', [
            'booking_id' => $booking?->id,
        ], AppNotification::PRIORITY_HIGH);

        $this->notifyClient($event, 'تم إرسال طلب الحجز', 'تم إرسال طلب الحجز إلى مقدم الخدمة وسيتم إشعارك عند الرد.', [
            'booking_id' => $booking?->id,
        ]);
    }

    protected function bookingAccepted(ServiceEvent $event): void
    {
        $this->notifyClient($event, 'تم قبول الحجز', 'تم قبول طلب الحجز الخاص بك.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);

        $this->notifyBusiness($event, 'تم تأكيد قبول الحجز', 'تم تسجيل قبول الحجز بنجاح.', [
            'booking_id' => $event->subject_id,
        ]);
    }

    protected function bookingRejected(ServiceEvent $event): void
    {
        $this->notifyClient($event, 'تم رفض الحجز', 'تم رفض طلب الحجز الخاص بك.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);
    }

    protected function bookingCancelled(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'تم إلغاء الحجز', 'تم إلغاء الحجز.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);
    }

    protected function bookingStarted(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'بدأ تنفيذ الحجز', 'تم بدء تنفيذ الحجز.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);
    }

    protected function bookingCompleted(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'تم إنهاء الحجز', 'تم إنهاء الحجز بنجاح.', [
            'booking_id' => $event->subject_id,
        ]);
    }

    protected function bookingClientConfirmed(ServiceEvent $event): void
    {
        $this->notifyBusiness($event, 'تأكيد العميل', 'قام العميل بتأكيد الحجز.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);
    }

    protected function bookingBusinessConfirmed(ServiceEvent $event): void
    {
        $this->notifyClient($event, 'تأكيد مقدم الخدمة', 'قام مقدم الخدمة بتأكيد الحجز.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);
    }

    protected function bookingDepositFrozen(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'تم تجميد الضمان', 'تم تجميد مبلغ الضمان الخاص بالحجز.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_HIGH);
    }

    protected function bookingDepositReleased(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'تم تحرير الضمان', 'تم تحرير مبلغ الضمان الخاص بالحجز.', [
            'booking_id' => $event->subject_id,
        ]);
    }

    protected function bookingDepositRefunded(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'تم استرداد الضمان', 'تم تنفيذ استرداد مبلغ الضمان الخاص بالحجز.', [
            'booking_id' => $event->subject_id,
        ]);
    }

    protected function bookingDisputeOpened(ServiceEvent $event): void
    {
        $this->notifyBoth($event, 'تم فتح نزاع', 'تم فتح نزاع على هذا الحجز.', [
            'booking_id' => $event->subject_id,
        ], AppNotification::PRIORITY_URGENT);
    }

    protected function notifyBoth(ServiceEvent $event, string $title, string $body, array $extra = [], string $priority = AppNotification::PRIORITY_NORMAL): void
    {
        $this->notifyBusiness($event, $title, $body, $extra, $priority);
        $this->notifyClient($event, $title, $body, $extra, $priority);
    }

    protected function notifyBusiness(ServiceEvent $event, string $title, string $body, array $extra = [], string $priority = AppNotification::PRIORITY_NORMAL): void
    {
        $this->notifyUser((int) $event->business_id, $event, $title, $body, $extra, $priority);
    }

    protected function notifyClient(ServiceEvent $event, string $title, string $body, array $extra = [], string $priority = AppNotification::PRIORITY_NORMAL): void
    {
        $this->notifyUser((int) $event->client_id, $event, $title, $body, $extra, $priority);
    }

    protected function notifyUser(int $userId, ServiceEvent $event, string $title, string $body, array $extra = [], string $priority = AppNotification::PRIORITY_NORMAL): void
    {
        if ($userId <= 0) {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $url = $this->urlForEvent($event);

        $user->notify(new ServiceEventDatabaseNotification(
            event: $event,
            title: $title,
            body: $body,
            url: $url,
            extra: $extra
        ));

        $exists = AppNotification::query()
            ->where('user_id', $userId)
            ->where('source_type', 'service_event')
            ->where('source_id', (int) $event->id)
            ->where('action_type', (string) $event->event_key)
            ->exists();

        if ($exists) {
            return;
        }

        $this->inAppNotificationService->create([
            'user_id' => $userId,
            'actor_id' => $event->actor_id ?: null,
            'type' => $this->appNotificationType($event),
            'channel' => AppNotification::CHANNEL_IN_APP,
            'priority' => $priority,
            'title_ar' => $title,
            'title_en' => null,
            'body_ar' => $body,
            'body_en' => null,
            'action_type' => (string) $event->event_key,
            'action_url' => $this->mobileActionUrl($event),
            'notifiable_type' => $event->subject_type,
            'notifiable_id' => $event->subject_id ? (int) $event->subject_id : null,
            'source_type' => 'service_event',
            'source_id' => (int) $event->id,
            'meta' => array_merge([
                'service_event_id' => (int) $event->id,
                'event_key' => (string) $event->event_key,
                'service_key' => (string) $event->service_key,
                'action_key' => (string) $event->action_key,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id ? (int) $event->subject_id : null,
                'actor_id' => $event->actor_id ? (int) $event->actor_id : null,
                'business_id' => $event->business_id ? (int) $event->business_id : null,
                'client_id' => $event->client_id ? (int) $event->client_id : null,
                'admin_url' => $url,
                'occurred_at' => optional($event->occurred_at)->toDateTimeString(),
                'payload' => $event->payload ?? [],
            ], $extra),
        ]);
    }

    protected function booking(ServiceEvent $event): ?Booking
    {
        if ((string) $event->subject_type !== (new Booking())->getMorphClass()) {
            return null;
        }

        if (! $event->subject_id) {
            return null;
        }

        return Booking::withTrashed()->find((int) $event->subject_id);
    }

    protected function urlForEvent(ServiceEvent $event): ?string
    {
        if (
            (string) $event->service_key === 'booking'
            && $event->subject_id
            && Route::has('admin.bookings.show')
        ) {
            return route('admin.bookings.show', (int) $event->subject_id);
        }

        return null;
    }

    protected function mobileActionUrl(ServiceEvent $event): ?string
    {
        if ((string) $event->service_key === 'booking' && $event->subject_id) {
            return '/bookings/' . (int) $event->subject_id;
        }

        return null;
    }

    protected function appNotificationType(ServiceEvent $event): string
    {
        return match ((string) $event->service_key) {
            'booking' => AppNotification::TYPE_BOOKING,
            default => AppNotification::TYPE_SYSTEM,
        };
    }

    protected function bookingReminder24h(ServiceEvent $event): void
    {
        $this->notifyReminderRecipient(
            event: $event,
            title: 'تذكير بموعد الحجز',
            body: 'تذكير: لديك حجز غدًا.'
        );
    }

    protected function bookingReminder1h(ServiceEvent $event): void
    {
        $this->notifyReminderRecipient(
            event: $event,
            title: 'تذكير قريب بموعد الحجز',
            body: 'تذكير: لديك حجز بعد ساعة تقريبًا.'
        );
    }

    protected function notifyReminderRecipient(ServiceEvent $event, string $title, string $body): void
    {
        $recipientId = (int) data_get($event->payload, 'recipient_id', 0);

        if ($recipientId <= 0) {
            return;
        }

        $this->notifyUser($recipientId, $event, $title, $body, [
            'booking_id' => $event->subject_id,
            'reminder_key' => data_get($event->payload, 'reminder_key'),
        ], AppNotification::PRIORITY_HIGH);
    }
}
