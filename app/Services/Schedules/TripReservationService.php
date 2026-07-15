<?php

namespace App\Services\Schedules;

use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Ratings\RatingService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The reservation lifecycle for the scheduling service: reserve capacity on a
 * leg (atomic, no overbooking), confirm, complete (→ rating for both parties),
 * cancel (→ release capacity, and ledger a cancel only once a real dealing had
 * started, i.e. after confirmation).
 */
final class TripReservationService
{
    public function __construct(
        private readonly RatingService $ratings,
        private readonly WalletService $wallet,
        private readonly NotificationDispatcherService $notifications
    ) {}

    /**
     * Reserve `units` on a leg. Capacity is checked under a row lock on the
     * schedule so concurrent reservations can never oversell it.
     */
    public function reserve(User $client, TripSchedule $schedule, int $units, ?string $notes = null): TripReservation
    {
        $units = max(1, $units);

        if ((string) $schedule->status !== TripSchedule::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['schedule' => 'خط التشغيل غير متاح للحجز.']);
        }

        if ((int) $schedule->business_id === (int) $client->id) {
            throw ValidationException::withMessages(['schedule' => 'لا يمكنك حجز خط تشغيل تملكه.']);
        }

        $reservation = DB::transaction(function () use ($client, $schedule, $units, $notes) {
            /** @var TripSchedule $locked */
            $locked = TripSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            $this->assertCapacity($locked, $units);

            $unitPrice = $locked->price !== null ? round((float) $locked->price, 2) : null;

            $reservation = TripReservation::create([
                'trip_schedule_id' => (int) $locked->id,
                'business_id' => (int) $locked->business_id,
                'client_id' => (int) $client->id,
                'units' => $units,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice !== null ? round($unitPrice * $units, 2) : null,
                'currency' => (string) $locked->currency,
                'source' => TripReservation::SOURCE_APP,
                'status' => TripReservation::STATUS_PENDING,
                'notes' => $notes,
            ]);

            // Optional refundable deposit: hold it now (rolls the whole thing
            // back — no reservation, no held capacity — if funds are short).
            $deposit = round((float) ($locked->deposit_per_unit ?? 0) * $units, 2);

            if ($deposit > 0) {
                $this->wallet->hold(
                    userId: (int) $client->id,
                    amount: $deposit,
                    referenceType: 'trip_reservation',
                    referenceId: (string) $reservation->id,
                    note: 'عربون حجز رحلة',
                    idempotencyKey: 'trip_res_hold_'.$reservation->id
                );

                $reservation->update(['deposit_held' => $deposit]);
            }

            return $reservation;
        });

        // Notify the carrier of the new reservation.
        $this->notify('trip_reservation_created', (int) $reservation->business_id, (int) $client->id, $reservation);

        return $reservation;
    }

    /**
     * Carrier blocks capacity for seats sold OFF the app (a direct deal). Holds
     * capacity like a real reservation but has no in-app client and never
     * touches the rating ledger. Released with cancel().
     */
    public function blockOffline(TripSchedule $schedule, int $units, ?string $notes = null): TripReservation
    {
        $units = max(1, $units);

        return DB::transaction(function () use ($schedule, $units, $notes) {
            /** @var TripSchedule $locked */
            $locked = TripSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            $this->assertCapacity($locked, $units);

            $unitPrice = $locked->price !== null ? round((float) $locked->price, 2) : null;

            return TripReservation::create([
                'trip_schedule_id' => (int) $locked->id,
                'business_id' => (int) $locked->business_id,
                'client_id' => null,
                'units' => $units,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice !== null ? round($unitPrice * $units, 2) : null,
                'currency' => (string) $locked->currency,
                'source' => TripReservation::SOURCE_OFFLINE,
                'status' => TripReservation::STATUS_BLOCKED,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Enforce the leg's capacity against everything already holding it. Null
     * capacity = unlimited (e.g. on-demand). Call inside the row-locked tx.
     */
    private function assertCapacity(TripSchedule $locked, int $units): void
    {
        if ($locked->capacity === null) {
            return;
        }

        $held = (int) TripReservation::query()
            ->where('trip_schedule_id', $locked->id)
            ->holdingCapacity()
            ->sum('units');

        if ($held + $units > (int) $locked->capacity) {
            throw ValidationException::withMessages([
                'units' => 'السعة المتاحة لا تكفي لهذا الحجز.',
            ]);
        }
    }

    /** Carrier accepts a pending reservation. */
    public function confirm(TripReservation $reservation): TripReservation
    {
        if ($reservation->status !== TripReservation::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'لا يمكن تأكيد هذا الحجز في حالته الحالية.']);
        }

        $reservation->update(['status' => TripReservation::STATUS_CONFIRMED]);

        // Notify the customer their reservation was accepted.
        $this->notify('trip_reservation_confirmed', (int) $reservation->client_id, (int) $reservation->business_id, $reservation);

        return $reservation->refresh();
    }

    /**
     * Complete a confirmed reservation → success for both parties in the
     * universal rating (carrier as business, sender/passenger as client).
     */
    public function complete(TripReservation $reservation): TripReservation
    {
        if ($reservation->status !== TripReservation::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => 'لا يمكن إكمال حجز غير مؤكد.']);
        }

        $result = DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => TripReservation::STATUS_COMPLETED]);

            $this->releaseDeposit($reservation);

            $this->ratings->recordForBothParties(
                businessUserId: (int) $reservation->business_id,
                clientUserId: (int) $reservation->client_id,
                outcome: \App\Models\RatingOutcomeEvent::OUTCOME_SUCCESS,
                operationType: TripReservation::OP_TRIP,
                operationId: (int) $reservation->id
            );

            return $reservation->refresh();
        });

        $this->notify('trip_reservation_completed', (int) $reservation->client_id, (int) $reservation->business_id, $reservation);

        return $result;
    }

    /**
     * Cancel a reservation and release its capacity. A cancel is only ledgered
     * against reputation once the carrier had confirmed (a real dealing); a
     * never-confirmed pending request cancels with no rating impact.
     */
    public function cancel(TripReservation $reservation, ?int $actorId = null): TripReservation
    {
        if (! in_array($reservation->status, [
            TripReservation::STATUS_PENDING,
            TripReservation::STATUS_CONFIRMED,
            TripReservation::STATUS_BLOCKED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'لا يمكن إلغاء هذا الحجز في حالته الحالية.']);
        }

        // Only a real, confirmed in-app dealing leaves a cancel on reputation.
        // Pending requests and carrier offline holds release with no rating hit.
        $ledgerCancel = $reservation->status === TripReservation::STATUS_CONFIRMED
            && $reservation->source === TripReservation::SOURCE_APP
            && (int) $reservation->client_id > 0;

        $result = DB::transaction(function () use ($reservation, $ledgerCancel) {
            $reservation->update(['status' => TripReservation::STATUS_CANCELLED]);

            // A held deposit is always returned to the client on cancel.
            $this->releaseDeposit($reservation);

            if ($ledgerCancel) {
                $this->ratings->recordForBothParties(
                    businessUserId: (int) $reservation->business_id,
                    clientUserId: (int) $reservation->client_id,
                    outcome: \App\Models\RatingOutcomeEvent::OUTCOME_CANCELLED,
                    operationType: TripReservation::OP_TRIP,
                    operationId: (int) $reservation->id
                );
            }

            return $reservation->refresh();
        });

        // Tell the party that did not initiate the cancellation.
        $recipient = $this->counterparty($reservation, $actorId);
        $this->notify('trip_reservation_cancelled', $recipient, $actorId ?? 0, $reservation);

        return $result;
    }

    private function releaseDeposit(TripReservation $reservation): void
    {
        $held = round((float) $reservation->deposit_held, 2);

        if ($held <= 0 || ! $reservation->client_id || data_get($reservation->meta, 'deposit_released')) {
            return;
        }

        $this->wallet->release(
            userId: (int) $reservation->client_id,
            amount: $held,
            referenceType: 'trip_reservation',
            referenceId: (string) $reservation->id,
            note: 'استرجاع عربون حجز رحلة',
            idempotencyKey: 'trip_res_release_'.$reservation->id
        );

        $reservation->update([
            'meta' => array_merge((array) ($reservation->meta ?? []), ['deposit_released' => true]),
        ]);
    }

    /** The party that did NOT act; defaults to the carrier when the actor is unknown. */
    private function counterparty(TripReservation $reservation, ?int $actorId): int
    {
        if ($actorId && (int) $actorId === (int) $reservation->client_id) {
            return (int) $reservation->business_id;
        }

        if ($actorId && (int) $actorId === (int) $reservation->business_id) {
            return (int) ($reservation->client_id ?? 0);
        }

        return (int) $reservation->business_id;
    }

    private function notify(string $eventKey, int $recipientId, int $actorId, TripReservation $reservation): void
    {
        if ($recipientId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($eventKey, $recipientId, [
                'actor_id' => $actorId > 0 ? $actorId : null,
                'notifiable_type' => TripReservation::class,
                'notifiable_id' => (int) $reservation->id,
                'source_id' => (int) $reservation->id,
                'service_type' => 'schedules',
                'meta' => [
                    'trip_schedule_id' => (int) $reservation->trip_schedule_id,
                    'units' => (int) $reservation->units,
                ],
            ]);
        } catch (\Throwable $e) {
            // Notifications are best-effort; never break the reservation flow.
        }
    }

    /**
     * Remaining capacity per schedule id (bulk, for search decoration). Null in
     * the result means "unlimited" (the leg has no capacity cap).
     *
     * @param  int[]  $scheduleIds
     * @return array<int, ?int>
     */
    public function remainingCapacityFor(array $scheduleIds): array
    {
        if (empty($scheduleIds)) {
            return [];
        }

        $capacities = TripSchedule::query()
            ->whereIn('id', $scheduleIds)
            ->pluck('capacity', 'id');

        $held = TripReservation::query()
            ->whereIn('trip_schedule_id', $scheduleIds)
            ->holdingCapacity()
            ->groupBy('trip_schedule_id')
            ->selectRaw('trip_schedule_id, SUM(units) as held')
            ->pluck('held', 'trip_schedule_id');

        $out = [];

        foreach ($scheduleIds as $id) {
            $capacity = $capacities[$id] ?? null;
            $out[$id] = $capacity === null
                ? null
                : max(0, (int) $capacity - (int) ($held[$id] ?? 0));
        }

        return $out;
    }

    /** Kept for symmetry / documentation of the role mapping. */
    public function roleForCarrier(): string
    {
        return UserOperationRating::ROLE_BUSINESS;
    }
}
