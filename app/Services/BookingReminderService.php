<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingReminder;
use App\Support\AdminV2\ServiceEvents\ServiceEventKeys;
use Illuminate\Support\Facades\DB;

class BookingReminderService
{
    public function __construct(
        protected ServiceEventDispatcher $serviceEventDispatcher
    ) {
    }

    public function scheduleForBooking(Booking $booking): void
    {
        $booking->refresh();

        if (! $booking->starts_at) {
            return;
        }

        if (! $booking->user_id || ! $booking->business_id) {
            return;
        }

        if ($booking->isFinalStatus()) {
            $this->cancelForBooking($booking);
            return;
        }

        $startsAt = $booking->starts_at;

        $rows = [
            [
                'recipient_id' => (int) $booking->user_id,
                'reminder_key' => BookingReminder::REMINDER_24H_CLIENT,
                'event_key' => ServiceEventKeys::BOOKING_REMINDER_24H,
                'scheduled_at' => $startsAt->copy()->subDay(),
            ],
            [
                'recipient_id' => (int) $booking->business_id,
                'reminder_key' => BookingReminder::REMINDER_24H_BUSINESS,
                'event_key' => ServiceEventKeys::BOOKING_REMINDER_24H,
                'scheduled_at' => $startsAt->copy()->subDay(),
            ],
            [
                'recipient_id' => (int) $booking->user_id,
                'reminder_key' => BookingReminder::REMINDER_1H_CLIENT,
                'event_key' => ServiceEventKeys::BOOKING_REMINDER_1H,
                'scheduled_at' => $startsAt->copy()->subHour(),
            ],
            [
                'recipient_id' => (int) $booking->business_id,
                'reminder_key' => BookingReminder::REMINDER_1H_BUSINESS,
                'event_key' => ServiceEventKeys::BOOKING_REMINDER_1H,
                'scheduled_at' => $startsAt->copy()->subHour(),
            ],
        ];

        DB::transaction(function () use ($booking, $rows) {
            foreach ($rows as $row) {
                if ($row['scheduled_at']->lessThanOrEqualTo(now())) {
                    continue;
                }

                BookingReminder::query()->updateOrCreate(
                    [
                        'booking_id' => (int) $booking->id,
                        'recipient_id' => (int) $row['recipient_id'],
                        'reminder_key' => (string) $row['reminder_key'],
                    ],
                    [
                        'event_key' => (string) $row['event_key'],
                        'scheduled_at' => $row['scheduled_at'],
                        'status' => BookingReminder::STATUS_PENDING,
                        'channel' => BookingReminder::CHANNEL_DATABASE,
                        'payload' => [
                            'booking_id' => (int) $booking->id,
                            'recipient_id' => (int) $row['recipient_id'],
                            'reminder_key' => (string) $row['reminder_key'],
                            'starts_at' => optional($booking->starts_at)->toDateTimeString(),
                            'ends_at' => optional($booking->ends_at)->toDateTimeString(),
                            'business_id' => (int) $booking->business_id,
                            'client_id' => (int) $booking->user_id,
                        ],
                    ]
                );
            }
        });
    }

    public function cancelForBooking(Booking $booking): void
    {
        BookingReminder::query()
            ->forBooking((int) $booking->id)
            ->pending()
            ->update([
                'status' => BookingReminder::STATUS_CANCELLED,
                'updated_at' => now(),
            ]);
    }

    public function sendDue(int $limit = 100): int
    {
        $sent = 0;

        $reminders = BookingReminder::query()
            ->with('booking')
            ->due()
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($reminders as $reminder) {
            try {
                $booking = $reminder->booking;

                if (! $booking || $booking->isFinalStatus()) {
                    $reminder->cancel();
                    continue;
                }

                $event = $this->dispatchReminderEvent($reminder, $booking);

                $reminder->markSent((int) $event->id);

                $sent++;
            } catch (\Throwable $e) {
                report($e);

                $reminder->markFailed($e->getMessage());
            }
        }

        return $sent;
    }

    protected function dispatchReminderEvent(BookingReminder $reminder, Booking $booking)
    {
        $payload = is_array($reminder->payload) ? $reminder->payload : [];

        $payload['recipient_id'] = (int) $reminder->recipient_id;
        $payload['reminder_id'] = (int) $reminder->id;
        $payload['reminder_key'] = (string) $reminder->reminder_key;
        $payload['scheduled_at'] = optional($reminder->scheduled_at)->toDateTimeString();

        if ((string) $reminder->event_key === ServiceEventKeys::BOOKING_REMINDER_24H) {
            return $this->serviceEventDispatcher->bookingReminder24h(
                booking: $booking,
                actorId: null,
                payload: $payload
            );
        }

        return $this->serviceEventDispatcher->bookingReminder1h(
            booking: $booking,
            actorId: null,
            payload: $payload
        );
    }
}