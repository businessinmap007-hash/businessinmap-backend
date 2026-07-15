<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\UserGuarantee;
use App\Models\UserOperationRating;
use App\Services\Ratings\RatingService;

/**
 * The single authority for the reputation-based coverage boost (rating slice 3).
 *
 * Given a guarantee that already qualifies for its level's FULL (active)
 * coverage, this decides the effective coverage: the higher
 * `boost_coverage_amount` when the user's OPERATION rating (success% / dispute%
 * / volume — the objective reputation, never the subjective stars) clears the
 * level's thresholds, otherwise the plain active coverage.
 *
 * Every place that assigns active coverage to a guarantee funnels through here,
 * so the boost is recomputed on each operation and self-reverts the moment the
 * rating deteriorates. It is never a stored, permanent grant.
 */
final class GuaranteeBoostEvaluator
{
    public function __construct(
        private readonly RatingService $ratings
    ) {}

    /**
     * Effective coverage for a guarantee holding an active, qualified level.
     *
     * @return array{is_boosted: bool, coverage_amount: float}
     */
    public function activeCoverageFor(UserGuarantee $guarantee, GuaranteeLevel $level): array
    {
        $active = round((float) $level->active_coverage_amount, 2);

        if (! $this->boostConfigured($level) || ! $this->qualifies($guarantee, $level)) {
            return ['is_boosted' => false, 'coverage_amount' => $active];
        }

        return [
            'is_boosted' => true,
            // Guard against a mis-set boost below active — never regress coverage.
            'coverage_amount' => max(round((float) $level->boost_coverage_amount, 2), $active),
        ];
    }

    /** A level offers a boost only when its boost coverage genuinely exceeds active. */
    public function boostConfigured(GuaranteeLevel $level): bool
    {
        return $level->boost_coverage_amount !== null
            && (float) $level->boost_coverage_amount > (float) $level->active_coverage_amount;
    }

    private function qualifies(UserGuarantee $guarantee, GuaranteeLevel $level): bool
    {
        $summary = $this->ratings->summaryFor(
            (int) $guarantee->user_id,
            $this->roleFor($guarantee)
        );

        if ((int) $summary['total_operations'] < (int) ($level->boost_min_operations ?? 0)) {
            return false;
        }

        if ((float) $summary['success_rate'] < (float) ($level->boost_min_success_rate ?? 0)) {
            return false;
        }

        if ($level->boost_max_dispute_rate !== null
            && (float) $summary['dispute_rate'] > (float) $level->boost_max_dispute_rate) {
            return false;
        }

        return true;
    }

    private function roleFor(UserGuarantee $guarantee): string
    {
        return (string) $guarantee->target_type === GuaranteeLevel::TARGET_BUSINESS
            ? UserOperationRating::ROLE_BUSINESS
            : UserOperationRating::ROLE_CLIENT;
    }
}
