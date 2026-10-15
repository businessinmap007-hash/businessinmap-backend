<?php

namespace App\Services\Schedules;

use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Ratings\RatingService;
use App\Services\VisitDepositService;
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
        private readonly NotificationDispatcherService $notifications,
        private readonly VisitDepositService $deposits,
    ) {}

    /**
     * Reserve `units` on a leg. Capacity is checked under a row lock on the
     * schedule so concurrent reservations can never oversell it.
     */
    public function reserve(User $client, TripSchedule $schedule, int $units, ?string $notes = null, bool $friendGuarantee = false): TripReservation
    {
        $units = max(1, $units);

        if ((string) $schedule->status !== TripSchedule::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['schedule' => __('خط التشغيل غير متاح للحجز.')]);
        }

        if ((int) $schedule->business_id === (int) $client->id) {
            throw ValidationException::withMessages(['schedule' => __('لا يمكنك حجز خط تشغيل تملكه.')]);
        }

        $reservation = DB::transaction(function () use ($client, $schedule, $units, $notes, $friendGuarantee) {
            /** @var TripSchedule $locked */
            $locked = TripSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            $this->assertBoardingOpen($locked);
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

            // The deposit (the carrier's own per unit, else its «شروط الحجز»): judged on the deposit core now — his
            // guarantee first, his wallet for the rest — and FROZEN when the carrier confirms. A customer who cannot
            // stand for it is refused (rolling the whole reservation back, no capacity taken) unless a friend is to
            // stand for him. `deposit_held` records the amount asked.
            $reservation->setRelation('schedule', $locked);
            $quote = $this->deposits->stateFor($reservation);

            if ($quote !== null) {
                if (! $friendGuarantee) {
                    $this->deposits->assertSubjectAffordable($reservation);
                }

                $reservation->update([
                    'deposit_held' => (float) $quote['amount'],
                    'meta' => array_merge((array) ($reservation->meta ?? []), ['deposit_core' => true]),
                ]);
            }

            return $reservation;
        });

        // Notify the carrier of the new reservation.
        $this->notify('trip_reservation_created', (int) $reservation->business_id, (int) $client->id, $reservation);

        $this->releaseNoShowDepositsIfFull((int) $reservation->trip_schedule_id);

        return $reservation;
    }

    /**
     * Carrier blocks capacity for seats sold OFF the app (a direct deal). Holds
     * capacity like a real reservation but has no in-app client and never
     * touches the rating ledger. Released with cancel().
     */
    public function blockOffline(
        TripSchedule $schedule,
        int $units,
        ?string $notes = null,
        ?string $passengerName = null,
        ?string $passengerPhone = null,
    ): TripReservation {
        $units = max(1, $units);

        $hold = DB::transaction(function () use ($schedule, $units, $notes, $passengerName, $passengerPhone) {
            /** @var TripSchedule $locked */
            $locked = TripSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            $this->assertBoardingOpen($locked);
            $this->assertCapacity($locked, $units);

            $unitPrice = $locked->price !== null ? round((float) $locked->price, 2) : null;

            return TripReservation::create([
                'trip_schedule_id' => (int) $locked->id,
                'business_id' => (int) $locked->business_id,
                'client_id' => null,
                'passenger_name' => $passengerName !== null ? trim($passengerName) : null,
                'passenger_phone' => $passengerPhone !== null ? trim($passengerPhone) : null,
                'units' => $units,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice !== null ? round($unitPrice * $units, 2) : null,
                'currency' => (string) $locked->currency,
                'source' => TripReservation::SOURCE_OFFLINE,
                'status' => TripReservation::STATUS_BLOCKED,
                'notes' => $notes,
                // there is no app on the customer's side: the carrier's word is the only confirmation
                'client_confirmed_at' => now(),
            ]);
        });

        // an off-app passenger taking the seat of a no-show: when that fills the trip the held deposit goes back
        $this->releaseNoShowDepositsIfFull((int) $hold->trip_schedule_id);

        return $hold;
    }

    /**
     * An off-app passenger appears in the ratings and the completed/pending counts only once the platform fee is paid —
     * the SAME fee an app customer pays. The carrier asks for it and it is taken from HIS wallet (the customer has no
     * account here). Idempotent: asking twice charges once.
     */
    public function makeRatingVisible(TripReservation $reservation): TripReservation
    {
        if ($reservation->source !== TripReservation::SOURCE_OFFLINE || $reservation->status !== TripReservation::STATUS_BLOCKED) {
            throw ValidationException::withMessages(['status' => __('الظهور في التقييم لراكب خارج التطبيق على حجز يدوي فقط.')]);
        }

        if ($reservation->rating_visible) {
            return $reservation;
        }

        $fee = $this->clientFeeFor($reservation);

        DB::transaction(function () use ($reservation, $fee) {
            if ($fee > 0) {
                $this->wallet->withdraw(
                    userId: (int) $reservation->business_id,
                    amount: $fee,
                    note: 'رسوم ظهور راكب خارج التطبيق في التقييم',
                    referenceType: 'trip_reservation',
                    referenceId: (string) $reservation->id,
                    idempotencyKey: 'trip_res_rating_fee_'.$reservation->id,
                );
            }

            $reservation->update(['rating_visible' => true, 'rating_fee' => $fee]);
        });

        return $reservation->refresh();
    }

    /** The client-side fee of this carrier's trade for the reservation's value (zero when none is set). */
    public function clientFeeFor(TripReservation $reservation): float
    {
        $business = User::query()->select(['id', 'category_id', 'category_child_id'])->find((int) $reservation->business_id);
        $base = round((float) $reservation->total_price, 2);

        if (! $business || ! $business->category_id || ! $business->category_child_id || $base <= 0) {
            return 0.0;
        }

        $fee = \App\Models\CategoryChildServiceFee::activeForRootChild((int) $business->category_id, (int) $business->category_child_id);

        if (! $fee || ! $fee->client_fee_enabled) {
            return 0.0;
        }

        $amount = (float) $fee->client_fee_amount;

        return round($fee->client_fee_type === \App\Models\CategoryChildServiceFee::CALC_TYPE_PERCENT ? $base * $amount / 100 : $amount, 2);
    }

    /**
     * A seat held for an off-app passenger whose fee was paid is completed like any other — and only the carrier's
     * side of the record takes the success (the passenger has no account to rate or be rated).
     */
    public function completeOffline(TripReservation $reservation): TripReservation
    {
        if ($reservation->source !== TripReservation::SOURCE_OFFLINE || $reservation->status !== TripReservation::STATUS_BLOCKED) {
            throw ValidationException::withMessages(['status' => __('لا يمكن إكمال هذا الحجز.')]);
        }

        if (! $reservation->rating_visible) {
            throw ValidationException::withMessages(['status' => __('يُسجَّل راكب خارج التطبيق مكتملًا بعد سداد الرسوم فقط.')]);
        }

        DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => TripReservation::STATUS_COMPLETED]);

            $this->ratings->recordOutcome(
                (int) $reservation->business_id,
                UserOperationRating::ROLE_BUSINESS,
                \App\Models\RatingOutcomeEvent::OUTCOME_SUCCESS,
                TripReservation::OP_TRIP,
                (int) $reservation->id
            );
        });

        return $reservation->refresh();
    }

    /* ------------------------------------------------------------ attendance */

    /** The customer says he is there / on his way aboard — always first. */
    public function confirmAttendanceByClient(TripReservation $reservation): TripReservation
    {
        if ($reservation->status !== TripReservation::STATUS_CONFIRMED || ! $reservation->client_id) {
            throw ValidationException::withMessages(['status' => __('يُؤكَّد الحضور على حجز مؤكَّد فقط.')]);
        }

        if (! $reservation->client_confirmed_at) {
            $reservation->update(['client_confirmed_at' => now()]);
        }

        $this->notify('trip_attendance_client_confirmed', (int) $reservation->business_id, (int) $reservation->client_id, $reservation);

        return $reservation->refresh();
    }

    /** The carrier confirms the passenger boarded — only after the customer did (an off-app seat needs only the carrier). */
    public function confirmAttendanceByCarrier(TripReservation $reservation): TripReservation
    {
        $isOfflineSeat = $reservation->source === TripReservation::SOURCE_OFFLINE && $reservation->status === TripReservation::STATUS_BLOCKED;

        if (! $isOfflineSeat && $reservation->status !== TripReservation::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => __('يُؤكَّد الحضور على حجز مؤكَّد فقط.')]);
        }

        if (! $isOfflineSeat && ! $reservation->client_confirmed_at) {
            throw ValidationException::withMessages(['attendance' => __('يؤكّد العميل حضوره أولًا، ثم تؤكّد أنت.')]);
        }

        if (! $reservation->carrier_confirmed_at) {
            $reservation->update(['carrier_confirmed_at' => now()]);
        }

        return $reservation->refresh();
    }

    /* -------------------------------------------------------------- capacity */

    /** Seats held on a leg: reservations that hold capacity plus the seats the accepted parcels occupy. */
    public function heldUnits(int $scheduleId): int
    {
        $reservations = (int) TripReservation::query()
            ->where('trip_schedule_id', $scheduleId)
            ->holdingCapacity()
            ->sum('units');

        $parcels = (int) \App\Models\TripParcel::query()
            ->where('trip_schedule_id', $scheduleId)
            ->whereIn('status', \App\Models\TripParcel::SEAT_HOLDING)
            ->sum('seats');

        return $reservations + $parcels;
    }

    /** Full: a capped leg with no free seat left. A leg with no cap is never full. */
    public function isFull(TripSchedule $schedule): bool
    {
        return $schedule->capacity !== null && $this->heldUnits((int) $schedule->id) >= (int) $schedule->capacity;
    }

    /**
     * Full AND every seat on it is attended by both sides: nothing may be added or replaced any more, the other customers
     * see it as complete, and only the driver's «بدء الرحلة» is left.
     */
    public function boardingClosed(TripSchedule $schedule): bool
    {
        if (! $this->isFull($schedule)) {
            return false;
        }

        $seats = TripReservation::query()
            ->where('trip_schedule_id', $schedule->id)
            // a still-pending request has no attendance at all, so it keeps the leg open
            ->whereIn('status', [TripReservation::STATUS_PENDING, TripReservation::STATUS_CONFIRMED, TripReservation::STATUS_BLOCKED])
            ->get(['id', 'client_confirmed_at', 'carrier_confirmed_at']);

        if ($seats->isEmpty()) {
            // a leg filled by parcels alone has no attendance to wait for
            return true;
        }

        return $seats->every(fn (TripReservation $r) => $r->client_confirmed_at && $r->carrier_confirmed_at);
    }

    private function assertBoardingOpen(TripSchedule $locked): void
    {
        if ($this->boardingClosed($locked)) {
            throw ValidationException::withMessages([
                'schedule' => __('الرحلة مكتملة وأُغلق الحجز عليها.'),
            ]);
        }
    }

    /**
     * The trip is full: a deposit still held for a no-show goes back to its customer at once. The carrier has no say —
     * a held deposit must never become a way to earn from a seat he has already sold again.
     */
    public function releaseNoShowDepositsIfFull(int $scheduleId): void
    {
        $schedule = TripSchedule::query()->find($scheduleId);

        if (! $schedule || ! $this->isFull($schedule)) {
            return;
        }

        TripReservation::query()
            ->where('trip_schedule_id', $scheduleId)
            ->where('status', TripReservation::STATUS_NO_SHOW)
            ->get()
            ->each(function (TripReservation $r) {
                if (! data_get($r->meta, 'deposit_core') || data_get($r->meta, 'deposit_returned_on_full')) {
                    return;
                }

                $this->deposits->safely(function () use ($r) {
                    $this->deposits->refund($r);
                    $r->update(['meta' => array_merge((array) ($r->meta ?? []), ['deposit_returned_on_full' => true])]);
                });
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

        $held = $this->heldUnits((int) $locked->id);

        if ($held + $units > (int) $locked->capacity) {
            throw ValidationException::withMessages([
                'units' => __('السعة المتاحة لا تكفي لهذا الحجز.'),
            ]);
        }
    }

    /** Carrier accepts a pending reservation. */
    public function confirm(TripReservation $reservation): TripReservation
    {
        if ($reservation->status !== TripReservation::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تأكيد هذا الحجز في حالته الحالية.')]);
        }

        DB::transaction(function () use ($reservation) {
            // frozen (or recorded as owed outside the platform) when the carrier takes the reservation; a short
            // wallet on either side refuses here with its reason and the reservation stays pending
            $this->deposits->secure($reservation);

            $reservation->update(['status' => TripReservation::STATUS_CONFIRMED]);
        });

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
            throw ValidationException::withMessages(['status' => __('لا يمكن إكمال حجز غير مؤكد.')]);
        }

        $result = DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => TripReservation::STATUS_COMPLETED]);

            $this->returnDeposit($reservation, completed: true);

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
            throw ValidationException::withMessages(['status' => __('لا يمكن إلغاء هذا الحجز في حالته الحالية.')]);
        }

        // Only a real, confirmed in-app dealing leaves a cancel on reputation.
        // Pending requests and carrier offline holds release with no rating hit.
        $ledgerCancel = $reservation->status === TripReservation::STATUS_CONFIRMED
            && $reservation->source === TripReservation::SOURCE_APP
            && (int) $reservation->client_id > 0;

        $result = DB::transaction(function () use ($reservation, $ledgerCancel) {
            $reservation->update(['status' => TripReservation::STATUS_CANCELLED]);

            // A held deposit is always returned on cancel — and whatever a friend froze for it.
            $this->returnDeposit($reservation, completed: false);

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

    /**
     * The carrier marks a confirmed reservation «لم يحضر». The deposit stays HELD (never confiscated): the carrier may
     * give it back at once, or either side opens a dispute. Only the customer's side of the record takes the mark.
     */
    public function noShow(TripReservation $reservation): TripReservation
    {
        if ($reservation->status !== TripReservation::STATUS_CONFIRMED || ! $reservation->client_id) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تسجيل عدم الحضور إلا لحجز مؤكد.')]);
        }

        $reservation->update(['status' => TripReservation::STATUS_NO_SHOW]);

        $this->ratings->recordOutcome(
            (int) $reservation->client_id,
            UserOperationRating::ROLE_CLIENT,
            \App\Models\RatingOutcomeEvent::OUTCOME_CANCELLED,
            TripReservation::OP_TRIP,
            (int) $reservation->id
        );

        $this->notify('trip_reservation_cancelled', (int) $reservation->client_id, (int) $reservation->business_id, $reservation);

        $this->releaseNoShowDepositsIfFull((int) $reservation->trip_schedule_id);

        return $reservation->refresh();
    }

    /** The deposit goes back to whoever posted it: through the core for a reservation made on it, else the old wallet hold. */
    private function returnDeposit(TripReservation $reservation, bool $completed): void
    {
        if (data_get($reservation->meta, 'deposit_core')) {
            $this->deposits->safely(fn () => $completed ? $this->deposits->release($reservation) : $this->deposits->refund($reservation));

            return;
        }

        $this->releaseDeposit($reservation);
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
            ->pluck('held', 'trip_schedule_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        foreach (\App\Models\TripParcel::query()
            ->whereIn('trip_schedule_id', $scheduleIds)
            ->whereIn('status', \App\Models\TripParcel::SEAT_HOLDING)
            ->groupBy('trip_schedule_id')
            ->selectRaw('trip_schedule_id, SUM(seats) as held')
            ->pluck('held', 'trip_schedule_id') as $id => $seats) {
            $held[$id] = ($held[$id] ?? 0) + (int) $seats;
        }

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
