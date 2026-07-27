<?php

namespace App\Observers;

use App\Models\AgendaItem;
use App\Models\Booking;
use App\Services\Agenda\AgendaService;
use Illuminate\Support\Carbon;

/**
 * Mirrors service bookings (a restaurant, a barber, …) onto the customer's
 * personal agenda, so they share one timeline with clinic appointments and
 * personal tasks — and a clinic appointment can't be placed over one. Purely a
 * side-effect: a failure here never blocks a booking.
 */
class BookingAgendaObserver
{
    /** Statuses that still occupy the customer's time. */
    private const ACTIVE = [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS];

    public function __construct(private readonly AgendaService $agenda)
    {
    }

    public function saved(Booking $booking): void
    {
        try {
            if (! $booking->user_id || ! $booking->starts_at) {
                return;
            }

            if (! in_array($booking->status, self::ACTIVE, true)) {
                $this->agenda->closeForSource(
                    $booking,
                    $booking->status === Booking::STATUS_COMPLETED ? AgendaItem::STATUS_DONE : AgendaItem::STATUS_CANCELLED,
                );
                return;
            }

            [$start, $end] = $this->agenda->blockingWindow(
                Carbon::parse($booking->starts_at),
                $booking->ends_at ? Carbon::parse($booking->ends_at) : null,
                (bool) $booking->all_day,
            );

            $this->agenda->syncCommitment(
                (int) $booking->user_id,
                AgendaItem::KIND_BOOKING,
                $booking,
                trim(__('حجز') . ' - ' . (string) ($booking->business?->name ?? '')),
                $start,
                $end,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function deleted(Booking $booking): void
    {
        try {
            $this->agenda->closeForSource($booking, AgendaItem::STATUS_CANCELLED);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
