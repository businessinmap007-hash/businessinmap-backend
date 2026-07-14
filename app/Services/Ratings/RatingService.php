<?php

namespace App\Services\Ratings;

use App\Models\RatingOutcomeEvent;
use App\Models\UserOperationRating;
use Illuminate\Support\Facades\DB;

/**
 * The single authority for the operation-based rating (objective reputation).
 * Every completed / cancelled / disputed operation is recorded here, once, and
 * feeds each party's per-role counts. Rates (success% / cancel% / dispute%) are
 * derived from the counts on read.
 *
 * This is universal — bookings AND menu/delivery orders, every user, whether or
 * not they hold a guarantee. It is independent of the guarantee trust_score,
 * which stays dedicated to guarantee-level gating.
 */
final class RatingService
{
    /**
     * Record an outcome for one party of one operation. Idempotent: the same
     * (operation, party, outcome) is counted at most once. Returns true when it
     * actually incremented, false when it was a duplicate no-op.
     */
    public function recordOutcome(
        int $rateeUserId,
        string $role,
        string $outcome,
        string $operationType,
        int $operationId
    ): bool {
        if ($rateeUserId <= 0
            || ! in_array($role, UserOperationRating::roles(), true)
            || ! in_array($outcome, RatingOutcomeEvent::outcomes(), true)) {
            return false;
        }

        return DB::transaction(function () use ($rateeUserId, $role, $outcome, $operationType, $operationId) {
            $event = RatingOutcomeEvent::query()->firstOrCreate([
                'operation_type' => $operationType,
                'operation_id' => $operationId,
                'ratee_user_id' => $rateeUserId,
                'outcome' => $outcome,
            ], [
                'role' => $role,
            ]);

            if (! $event->wasRecentlyCreated) {
                return false; // already counted
            }

            $rating = UserOperationRating::query()
                ->where('user_id', $rateeUserId)
                ->where('role', $role)
                ->lockForUpdate()
                ->first();

            if (! $rating) {
                $rating = new UserOperationRating([
                    'user_id' => $rateeUserId,
                    'role' => $role,
                ]);
            }

            $rating->total_operations = (int) $rating->total_operations + 1;
            $rating->{$this->counterColumn($outcome)} = (int) $rating->{$this->counterColumn($outcome)} + 1;
            $rating->save();

            return true;
        });
    }

    /**
     * Record an outcome for BOTH parties of an operation that has a business and
     * a client side (bookings, menu/delivery orders).
     */
    public function recordForBothParties(
        int $businessUserId,
        int $clientUserId,
        string $outcome,
        string $operationType,
        int $operationId
    ): void {
        $this->recordOutcome($clientUserId, UserOperationRating::ROLE_CLIENT, $outcome, $operationType, $operationId);
        $this->recordOutcome($businessUserId, UserOperationRating::ROLE_BUSINESS, $outcome, $operationType, $operationId);
    }

    /**
     * Rating summary for a user in a role. Always returns a well-formed payload,
     * even when the user has no recorded operations yet.
     *
     * @return array{role: string, total_operations: int, success_count: int, cancelled_count: int, disputed_count: int, success_rate: float, cancel_rate: float, dispute_rate: float}
     */
    public function summaryFor(int $userId, string $role): array
    {
        $rating = UserOperationRating::query()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->first();

        return [
            'role' => $role,
            'total_operations' => (int) ($rating->total_operations ?? 0),
            'success_count' => (int) ($rating->success_count ?? 0),
            'cancelled_count' => (int) ($rating->cancelled_count ?? 0),
            'disputed_count' => (int) ($rating->disputed_count ?? 0),
            'success_rate' => $rating ? $rating->successRate() : 0.0,
            'cancel_rate' => $rating ? $rating->cancelRate() : 0.0,
            'dispute_rate' => $rating ? $rating->disputeRate() : 0.0,
        ];
    }

    private function counterColumn(string $outcome): string
    {
        return match ($outcome) {
            RatingOutcomeEvent::OUTCOME_SUCCESS => 'success_count',
            RatingOutcomeEvent::OUTCOME_CANCELLED => 'cancelled_count',
            RatingOutcomeEvent::OUTCOME_DISPUTED => 'disputed_count',
        };
    }
}
