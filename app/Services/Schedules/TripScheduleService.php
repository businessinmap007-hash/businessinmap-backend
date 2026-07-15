<?php

namespace App\Services\Schedules;

use App\Models\TripSchedule;
use App\Models\UserGuarantee;
use App\Models\UserOperationRating;
use App\Services\Ratings\RatingService;
use Illuminate\Support\Collection;

/**
 * Search the published trip schedules and rank matches by trust.
 *
 * The point of the scheduling service is not just "who goes Cairo→Damietta on
 * Sunday" but "who is TRUSTWORTHY for it" — so every match is decorated with
 * the business's guarantee coverage and its operation rating (success% /
 * dispute% / stars), then ordered so the safest carriers surface first.
 */
final class TripScheduleService
{
    public function __construct(
        private readonly RatingService $ratings,
        private readonly TripReservationService $reservations
    ) {}

    /**
     * @param  array{origin_governorate_id:int, destination_governorate_id:int, date?:?string, day_of_week?:?int, mode?:?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function search(array $filters): Collection
    {
        $originGov = (int) ($filters['origin_governorate_id'] ?? 0);
        $destGov = (int) ($filters['destination_governorate_id'] ?? 0);

        if ($originGov <= 0 || $destGov <= 0) {
            return collect();
        }

        [$dayOfWeek, $date] = $this->resolveDay($filters);

        $query = TripSchedule::query()
            ->active()
            ->where('origin_governorate_id', $originGov)
            ->where('destination_governorate_id', $destGov)
            ->with([
                'business:id,name,logo,image',
                'originGovernorate:id,name_ar,name_en',
                'destinationGovernorate:id,name_ar,name_en',
            ]);

        if (! empty($filters['mode'])) {
            $query->where('mode', (string) $filters['mode']);
        }

        if ($dayOfWeek !== null) {
            $query->matchingDay($dayOfWeek, $date);
        }

        $schedules = $query->limit(200)->get();

        if ($schedules->isEmpty()) {
            return collect();
        }

        $trustByBusiness = $this->trustForBusinesses(
            $schedules->pluck('business_id')->map(fn ($id) => (int) $id)->unique()->all()
        );

        $remaining = $this->reservations->remainingCapacityFor(
            $schedules->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        return $schedules
            ->map(function (TripSchedule $schedule) use ($trustByBusiness, $remaining) {
                $trust = $trustByBusiness[(int) $schedule->business_id] ?? $this->emptyTrust();

                return [
                    'schedule' => $schedule,
                    'trust' => $trust,
                    'remaining_capacity' => $remaining[(int) $schedule->id] ?? null,
                    'rank_key' => $this->rankKey($trust),
                ];
            })
            ->sortByDesc('rank_key')
            ->values();
    }

    /**
     * Bulk trust lookup (guarantee coverage + operation rating) for a set of
     * businesses — one query per source, no per-row N+1.
     *
     * @param  int[]  $businessIds
     * @return array<int, array<string, mixed>>
     */
    public function trustForBusinesses(array $businessIds): array
    {
        if (empty($businessIds)) {
            return [];
        }

        // Active guarantee coverage per business (latest active row wins).
        // Ascending id so keyBy keeps the highest-id (newest) row per user.
        $guarantees = UserGuarantee::query()
            ->whereIn('user_id', $businessIds)
            ->where('target_type', 'business')
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->orderBy('id')
            ->get()
            ->keyBy('user_id');

        // Operation rating per business (business role).
        $ratings = UserOperationRating::query()
            ->whereIn('user_id', $businessIds)
            ->where('role', UserOperationRating::ROLE_BUSINESS)
            ->get()
            ->keyBy('user_id');

        $out = [];

        foreach ($businessIds as $id) {
            $guarantee = $guarantees->get($id);
            $rating = $ratings->get($id);

            $out[$id] = [
                'has_active_guarantee' => (bool) $guarantee,
                'is_boosted' => $guarantee ? (bool) $guarantee->is_boosted : false,
                'available_coverage' => $guarantee ? round((float) $guarantee->availableCoverage(), 2) : 0.0,
                'total_operations' => $rating ? (int) $rating->total_operations : 0,
                'success_rate' => $rating ? $rating->successRate() : 0.0,
                'dispute_rate' => $rating ? $rating->disputeRate() : 0.0,
                'stars_average' => $rating && (int) $rating->review_count > 0
                    ? round((int) $rating->review_stars_sum / (int) $rating->review_count, 2)
                    : 0.0,
                'review_count' => $rating ? (int) $rating->review_count : 0,
            ];
        }

        return $out;
    }

    /** Resolve either an explicit day_of_week or one derived from a date. */
    private function resolveDay(array $filters): array
    {
        if (isset($filters['day_of_week']) && $filters['day_of_week'] !== null && $filters['day_of_week'] !== '') {
            $dow = (int) $filters['day_of_week'];

            return [max(0, min(6, $dow)), null];
        }

        $date = $filters['date'] ?? null;

        if ($date) {
            $ts = strtotime((string) $date);

            if ($ts !== false) {
                return [(int) date('w', $ts), date('Y-m-d', $ts)];
            }
        }

        return [null, null];
    }

    /**
     * Sortable composite: guaranteed carriers first, then higher success, more
     * coverage, fewer disputes, better stars. Packed into one descending number.
     */
    private function rankKey(array $trust): float
    {
        return ((bool) $trust['has_active_guarantee'] ? 1_000_000_000 : 0)
            + ((float) $trust['success_rate'] * 1_000_000)
            + min((float) $trust['available_coverage'], 999_999)
            - ((float) $trust['dispute_rate'] * 2_000_000)
            + ((float) $trust['stars_average'] * 100);
    }

    private function emptyTrust(): array
    {
        return [
            'has_active_guarantee' => false,
            'is_boosted' => false,
            'available_coverage' => 0.0,
            'total_operations' => 0,
            'success_rate' => 0.0,
            'dispute_rate' => 0.0,
            'stars_average' => 0.0,
            'review_count' => 0,
        ];
    }
}
