<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\RatingOutcomeEvent;
use App\Models\UserGuarantee;
use App\Models\UserOperationRating;
use App\Services\Guarantees\GuaranteeAutoDowngradeService;
use App\Services\Guarantees\GuaranteeBoostEvaluator;
use App\Services\Ratings\RatingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Rating slice 3: the reputation-based coverage boost. An excellent OPERATION
 * rating lifts coverage above the level's active amount (capped by the level's
 * boost_coverage_amount), and it drops back the moment the rating deteriorates.
 * Rolls back.
 */
class GuaranteeCoverageBoostTest extends TestCase
{
    use DatabaseTransactions;

    // A synthetic user id with no pre-existing rating rows → isolated aggregates.
    private const RATEE_ID = 999777;

    private function ratings(): RatingService
    {
        return app(RatingService::class);
    }

    private function evaluator(): GuaranteeBoostEvaluator
    {
        return app(GuaranteeBoostEvaluator::class);
    }

    /** A level offering 600 active / 750 boost, gated on good behaviour. */
    private function level(): GuaranteeLevel
    {
        return (new GuaranteeLevel())->forceFill([
            'id' => 900123,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'active_coverage_amount' => 600,
            'boost_coverage_amount' => 750,
            'boost_min_operations' => 3,
            'boost_min_success_rate' => 90,
            'boost_max_dispute_rate' => 5,
        ]);
    }

    private function guarantee(): UserGuarantee
    {
        return (new UserGuarantee())->forceFill([
            'user_id' => self::RATEE_ID,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
        ]);
    }

    private function recordSuccesses(int $count, int $startId = 1, ?int $rateeId = null): void
    {
        $rateeId ??= self::RATEE_ID;

        for ($i = 0; $i < $count; $i++) {
            $this->ratings()->recordOutcome(
                $rateeId,
                UserOperationRating::ROLE_CLIENT,
                RatingOutcomeEvent::OUTCOME_SUCCESS,
                RatingOutcomeEvent::OP_BOOKING,
                $startId + $i
            );
        }
    }

    public function test_no_operations_gives_plain_active_coverage(): void
    {
        $result = $this->evaluator()->activeCoverageFor($this->guarantee(), $this->level());

        $this->assertFalse($result['is_boosted']);
        $this->assertSame(600.0, $result['coverage_amount']);
    }

    public function test_excellent_rating_unlocks_the_boost(): void
    {
        $this->recordSuccesses(5); // total 5, success 100%, dispute 0%

        $result = $this->evaluator()->activeCoverageFor($this->guarantee(), $this->level());

        $this->assertTrue($result['is_boosted']);
        $this->assertSame(750.0, $result['coverage_amount']);
    }

    public function test_too_few_operations_does_not_qualify(): void
    {
        $this->recordSuccesses(2); // below boost_min_operations = 3

        $result = $this->evaluator()->activeCoverageFor($this->guarantee(), $this->level());

        $this->assertFalse($result['is_boosted']);
        $this->assertSame(600.0, $result['coverage_amount']);
    }

    public function test_high_dispute_rate_blocks_the_boost(): void
    {
        $this->recordSuccesses(5);
        // One dispute → total 6, dispute ≈16.7% (> 5%) and success ≈83% (< 90%).
        $this->ratings()->recordOutcome(
            self::RATEE_ID,
            UserOperationRating::ROLE_CLIENT,
            RatingOutcomeEvent::OUTCOME_DISPUTED,
            RatingOutcomeEvent::OP_BOOKING,
            9001
        );

        $result = $this->evaluator()->activeCoverageFor($this->guarantee(), $this->level());

        $this->assertFalse($result['is_boosted']);
        $this->assertSame(600.0, $result['coverage_amount']);
    }

    public function test_boost_is_disabled_when_level_has_no_boost_amount(): void
    {
        $this->recordSuccesses(5);

        $level = $this->level();
        $level->boost_coverage_amount = null;

        $result = $this->evaluator()->activeCoverageFor($this->guarantee(), $level);

        $this->assertFalse($result['is_boosted']);
        $this->assertSame(600.0, $result['coverage_amount']);
    }

    /**
     * End-to-end through the authoritative sync path: an excellent rating boosts
     * current_coverage_amount, and a later dispute reverts it on the next sync.
     */
    public function test_sync_applies_and_then_reverts_boost(): void
    {
        // Persisted guarantees need a real user (FK). Start from a clean rating slate.
        $user = \App\Models\User::query()->orderBy('id')->firstOrFail();
        UserOperationRating::query()->where('user_id', $user->id)->delete();
        RatingOutcomeEvent::query()->where('ratee_user_id', $user->id)->delete();

        $level = GuaranteeLevel::create([
            'code' => 'boost_test_'.uniqid(),
            'name_ar' => 'اختبار التعزيز',
            'name_en' => 'Boost Test',
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'required_locked_amount' => 500,
            'pending_coverage_amount' => 300,
            'active_coverage_amount' => 600,
            'boost_coverage_amount' => 750,
            'boost_min_operations' => 3,
            'boost_min_success_rate' => 90,
            'boost_max_dispute_rate' => 5,
            'required_completed_operations' => 0,
            'required_trust_score' => 0,
            'max_lost_disputes' => null,
            'max_late_cancellations' => null,
            'priority' => 100000,
            'is_active' => 1,
        ]);

        $guarantee = UserGuarantee::create([
            'user_id' => $user->id,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $level->id,
            'effective_level_id' => $level->id,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'locked_amount' => 500,
            'pending_coverage_amount' => 300,
            'active_coverage_amount' => 600,
            'current_coverage_amount' => 600,
            'used_coverage_amount' => 0,
            'completed_operations_count' => 0,
            'trust_score' => 0,
        ]);

        $this->recordSuccesses(5, 1, (int) $user->id);

        $sync = app(GuaranteeAutoDowngradeService::class);
        $boosted = $sync->syncEffectiveLevel($guarantee->refresh())['guarantee'];

        $this->assertTrue((bool) $boosted->is_boosted);
        $this->assertSame('750.00', (string) $boosted->current_coverage_amount);

        // A dispute drags the rating under the thresholds → boost drops off.
        $this->ratings()->recordOutcome(
            (int) $user->id,
            UserOperationRating::ROLE_CLIENT,
            RatingOutcomeEvent::OUTCOME_DISPUTED,
            RatingOutcomeEvent::OP_BOOKING,
            9002
        );

        $reverted = $sync->syncEffectiveLevel($boosted->refresh())['guarantee'];

        $this->assertFalse((bool) $reverted->is_boosted);
        $this->assertSame('600.00', (string) $reverted->current_coverage_amount);
    }
}
