<?php

namespace App\Services\Ratings;

use App\Models\Booking;
use App\Models\OperationReview;
use App\Models\Order;
use App\Models\RatingOutcomeEvent;
use App\Models\User;
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

        $reviewCount = (int) ($rating->review_count ?? 0);
        $starsSum = (int) ($rating->review_stars_sum ?? 0);

        return [
            'role' => $role,
            'total_operations' => (int) ($rating->total_operations ?? 0),
            'success_count' => (int) ($rating->success_count ?? 0),
            'cancelled_count' => (int) ($rating->cancelled_count ?? 0),
            'disputed_count' => (int) ($rating->disputed_count ?? 0),
            'success_rate' => $rating ? $rating->successRate() : 0.0,
            'cancel_rate' => $rating ? $rating->cancelRate() : 0.0,
            'dispute_rate' => $rating ? $rating->disputeRate() : 0.0,
            // Subjective star review aggregate.
            'review_count' => $reviewCount,
            'stars_average' => $reviewCount > 0 ? round($starsSum / $reviewCount, 2) : 0.0,
        ];
    }

    /**
     * Submit (or update) a subjective star review. A review is only allowed for a
     * real, COMPLETED operation the rater took part in — you can only rate the
     * counterparty you actually dealt with, which blocks reputation attacks from
     * strangers with no prior dealing.
     *
     * @return array{ok: bool, status: int, message: ?string, review: ?OperationReview}
     */
    public function submitReview(
        User $rater,
        string $operationType,
        int $operationId,
        int $stars,
        ?string $comment = null
    ): array {
        if (! in_array($operationType, [RatingOutcomeEvent::OP_BOOKING, RatingOutcomeEvent::OP_ORDER], true)) {
            return $this->reviewError(422, __('نوع العملية غير صالح.'));
        }

        if ($stars < 1 || $stars > 5) {
            return $this->reviewError(422, __('التقييم يجب أن يكون بين 1 و5 نجوم.'));
        }

        $operation = $this->resolveOperation($operationType, $operationId);

        if (! $operation) {
            return $this->reviewError(404, __('العملية غير موجودة.'));
        }

        $businessId = (int) $operation->business_id;
        $clientId = (int) $operation->user_id;
        $raterId = (int) $rater->id;

        // Must be a real, finished dealing.
        if ((string) $operation->status !== 'completed') {
            return $this->reviewError(409, __('لا يمكن التقييم إلا بعد اكتمال العملية.'));
        }

        // The rater must have actually been part of this operation.
        if (! in_array($raterId, [$businessId, $clientId], true)) {
            return $this->reviewError(403, __('لا يمكنك تقييم عملية لست طرفاً فيها.'));
        }

        // The ratee is the OTHER party, rated in the role they acted in.
        if ($raterId === $clientId) {
            $rateeId = $businessId;
            $rateeRole = UserOperationRating::ROLE_BUSINESS;
        } else {
            $rateeId = $clientId;
            $rateeRole = UserOperationRating::ROLE_CLIENT;
        }

        if ($rateeId <= 0 || $rateeId === $raterId) {
            return $this->reviewError(422, __('لا يوجد طرف آخر لتقييمه في هذه العملية.'));
        }

        $review = DB::transaction(function () use ($operationType, $operationId, $raterId, $rateeId, $rateeRole, $stars, $comment) {
            $existing = OperationReview::query()
                ->where('operation_type', $operationType)
                ->where('operation_id', $operationId)
                ->where('rater_id', $raterId)
                ->lockForUpdate()
                ->first();

            $aggregate = UserOperationRating::query()
                ->where('user_id', $rateeId)
                ->where('role', $rateeRole)
                ->lockForUpdate()
                ->first()
                ?? new UserOperationRating(['user_id' => $rateeId, 'role' => $rateeRole]);

            if ($existing) {
                // Update: shift the running sum by the delta only.
                $aggregate->review_stars_sum = max((int) $aggregate->review_stars_sum - (int) $existing->stars + $stars, 0);
                $existing->stars = $stars;
                $existing->comment = $comment;
                $existing->save();
                $review = $existing;
            } else {
                $review = OperationReview::create([
                    'operation_type' => $operationType,
                    'operation_id' => $operationId,
                    'rater_id' => $raterId,
                    'ratee_id' => $rateeId,
                    'ratee_role' => $rateeRole,
                    'stars' => $stars,
                    'comment' => $comment,
                ]);
                $aggregate->review_count = (int) $aggregate->review_count + 1;
                $aggregate->review_stars_sum = (int) $aggregate->review_stars_sum + $stars;
            }

            $aggregate->save();

            return $review;
        });

        return ['ok' => true, 'status' => 201, 'message' => null, 'review' => $review];
    }

    /** Reviews received by a user in a role, most recent first. */
    public function reviewsFor(int $userId, string $role, int $perPage = 20)
    {
        return OperationReview::query()
            ->with('rater:id,name,type,logo,image')
            ->where('ratee_id', $userId)
            ->where('ratee_role', $role)
            ->latest('id')
            ->paginate($perPage);
    }

    private function resolveOperation(string $operationType, int $operationId): Booking|Order|null
    {
        return match ($operationType) {
            RatingOutcomeEvent::OP_BOOKING => Booking::query()->find($operationId),
            RatingOutcomeEvent::OP_ORDER => Order::query()->find($operationId),
            default => null,
        };
    }

    private function reviewError(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message, 'review' => null];
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
