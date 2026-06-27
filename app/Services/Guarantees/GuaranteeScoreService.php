<?php

namespace App\Services\Guarantees;

use App\Models\UserGuarantee;

class GuaranteeScoreService
{
    public function __construct(
        protected GuaranteeAutoDowngradeService $guaranteeAutoDowngradeService
    ) {
    }

    public function record(UserGuarantee $guarantee, string $result): UserGuarantee
    {
        match ($result) {
            'completed' => $guarantee->completed_operations_count++,
            'cancelled' => $guarantee->cancelled_operations_count++,
            'late_cancelled' => $guarantee->late_cancellations_count++,
            'dispute_opened' => $guarantee->disputes_opened_count++,
            'dispute_lost' => $guarantee->disputes_lost_count++,
            default => null,
        };

        $guarantee->trust_score = $this->calculateTrustScore($guarantee);

        $this->maybeActivatePurchasedLevel($guarantee);

        $guarantee->save();

        $syncResult = $this->guaranteeAutoDowngradeService->syncEffectiveLevel(
            guarantee: $guarantee->refresh(),
            referenceType: 'guarantee_score',
            referenceId: (int) $guarantee->id,
            meta: [
                'score_result' => $result,
                'source' => 'GuaranteeScoreService::record',
            ]
        );

        return $syncResult['guarantee']->refresh();
    }

    protected function maybeActivatePurchasedLevel(UserGuarantee $guarantee): void
    {
        $level = $guarantee->purchasedLevel;

        if (! $level) {
            return;
        }

        $completedOk = (int) $guarantee->completed_operations_count >= (int) $level->required_completed_operations;

        $scoreOk = (float) $guarantee->trust_score >= (float) $level->required_trust_score;

        $lostDisputesOk = $level->max_lost_disputes === null
            || (int) $guarantee->disputes_lost_count <= (int) $level->max_lost_disputes;

        $lateCancelOk = $level->max_late_cancellations === null
            || (int) $guarantee->late_cancellations_count <= (int) $level->max_late_cancellations;

        if ($completedOk && $scoreOk && $lostDisputesOk && $lateCancelOk) {
            $guarantee->effective_level_id = (int) $level->id;
            $guarantee->status = UserGuarantee::STATUS_ACTIVE;
            $guarantee->current_coverage_amount = (float) $level->active_coverage_amount;
            $guarantee->activated_at = $guarantee->activated_at ?: now();

            return;
        }

        if ($guarantee->status !== UserGuarantee::STATUS_UNDERFUNDED) {
            $guarantee->effective_level_id = null;
            $guarantee->status = UserGuarantee::STATUS_PENDING_OPERATIONS;
            $guarantee->current_coverage_amount = (float) $level->pending_coverage_amount;
        }
    }

    protected function calculateTrustScore(UserGuarantee $guarantee): float
    {
        $score = 50;

        $score += min((int) $guarantee->completed_operations_count * 2, 40);
        $score -= min((int) $guarantee->cancelled_operations_count * 3, 20);
        $score -= min((int) $guarantee->late_cancellations_count * 5, 25);
        $score -= min((int) $guarantee->disputes_opened_count * 2, 15);
        $score -= min((int) $guarantee->disputes_lost_count * 15, 40);

        return round(max(min($score, 100), 0), 2);
    }
}
