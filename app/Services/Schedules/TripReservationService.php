<?php

namespace App\Services\Schedules;

use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\Ratings\RatingService;
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
        private readonly RatingService $ratings
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

        return DB::transaction(function () use ($client, $schedule, $units, $notes) {
            /** @var TripSchedule $locked */
            $locked = TripSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            $this->assertCapacity($locked, $units);

            $unitPrice = $locked->price !== null ? round((float) $locked->price, 2) : null;

            return TripReservation::create([
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
        });
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

        return DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => TripReservation::STATUS_COMPLETED]);

            $this->ratings->recordForBothParties(
                businessUserId: (int) $reservation->business_id,
                clientUserId: (int) $reservation->client_id,
                outcome: \App\Models\RatingOutcomeEvent::OUTCOME_SUCCESS,
                operationType: TripReservation::OP_TRIP,
                operationId: (int) $reservation->id
            );

            return $reservation->refresh();
        });
    }

    /**
     * Cancel a reservation and release its capacity. A cancel is only ledgered
     * against reputation once the carrier had confirmed (a real dealing); a
     * never-confirmed pending request cancels with no rating impact.
     */
    public function cancel(TripReservation $reservation): TripReservation
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

        return DB::transaction(function () use ($reservation, $ledgerCancel) {
            $reservation->update(['status' => TripReservation::STATUS_CANCELLED]);

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
