<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Services\Guarantees\GuaranteeCoverageException;
use App\Services\Guarantees\GuaranteeCoverageService;
use App\Services\Guarantees\GuaranteeOperationCoverageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

/**
 * Gap #1 coverage: GuaranteeOperationCoverageService — the second coverage gate
 * the engine consults before an operation. Its decision branches (missing /
 * not-usable / insufficient / covered), amount normalization, and the
 * requireCoverage throw are isolated by stubbing GuaranteeCoverageService, so no
 * guarantee/wallet scaffolding is needed. Rolls back.
 */
class GuaranteeOperationCoverageTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::query()->orderBy('id')->firstOrFail();
    }

    /** Build the service with a stubbed coverage lookup returning $guarantee. */
    private function service(?UserGuarantee $guarantee): GuaranteeOperationCoverageService
    {
        $coverage = Mockery::mock(GuaranteeCoverageService::class);
        $coverage->shouldReceive('activeGuarantee')->andReturn($guarantee);

        return new GuaranteeOperationCoverageService($coverage);
    }

    private function guarantee(string $status, float $current, float $used = 0.0): UserGuarantee
    {
        return (new UserGuarantee())->forceFill([
            'id' => 999001,
            'status' => $status,
            'locked_amount' => 1000,
            'current_coverage_amount' => $current,
            'used_coverage_amount' => $used,
        ]);
    }

    private function check(GuaranteeOperationCoverageService $service, float $amount): array
    {
        return $service->check(
            user: $this->user,
            amount: $amount,
            operationType: GuaranteeOperationCoverageService::OP_BOOKING,
            targetType: GuaranteeLevel::TARGET_CLIENT,
        );
    }

    public function test_missing_guarantee_is_not_covered(): void
    {
        $decision = $this->check($this->service(null), 100);

        $this->assertFalse($decision['covered']);
        $this->assertSame('missing_guarantee', $decision['reason']);
        $this->assertNull($decision['guarantee_id']);
    }

    public function test_unusable_guarantee_is_not_covered_even_with_funds(): void
    {
        // Suspended → not usable; funds are ample, but the usability gate runs first.
        $decision = $this->check($this->service($this->guarantee(UserGuarantee::STATUS_SUSPENDED, 1000)), 100);

        $this->assertFalse($decision['covered']);
        $this->assertSame('guarantee_not_usable', $decision['reason']);
    }

    public function test_insufficient_coverage_is_not_covered(): void
    {
        // Active but available (100 - 0) < 500.
        $decision = $this->check($this->service($this->guarantee(UserGuarantee::STATUS_ACTIVE, 100)), 500);

        $this->assertFalse($decision['covered']);
        $this->assertSame('insufficient_coverage', $decision['reason']);
        $this->assertSame(100.0, $decision['available_coverage_amount']);
    }

    public function test_sufficient_active_guarantee_is_covered(): void
    {
        // Active, available 900 (1000 - 100) ≥ 500.
        $decision = $this->check($this->service($this->guarantee(UserGuarantee::STATUS_ACTIVE, 1000, 100)), 500);

        $this->assertTrue($decision['covered']);
        $this->assertSame('covered', $decision['reason']);
        $this->assertSame(900.0, $decision['available_coverage_amount']);
        $this->assertSame(999001, $decision['guarantee_id']);
    }

    public function test_negative_amount_is_normalized_to_zero(): void
    {
        $decision = $this->check($this->service($this->guarantee(UserGuarantee::STATUS_ACTIVE, 1000)), -50);

        $this->assertSame(0.0, $decision['amount']);
        $this->assertTrue($decision['covered']); // covers(0) is always true for a usable guarantee
    }

    public function test_require_coverage_throws_when_not_covered(): void
    {
        $service = $this->service(null);

        try {
            $service->requireCoverage(
                user: $this->user,
                amount: 100,
                operationType: GuaranteeOperationCoverageService::OP_BOOKING,
                targetType: GuaranteeLevel::TARGET_CLIENT,
            );
            $this->fail('Expected GuaranteeCoverageException.');
        } catch (GuaranteeCoverageException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame('missing_guarantee', $e->decision()['reason']);
        }
    }

    public function test_require_coverage_returns_the_decision_when_covered(): void
    {
        $service = $this->service($this->guarantee(UserGuarantee::STATUS_ACTIVE, 1000));

        $decision = $service->requireCoverage(
            user: $this->user,
            amount: 100,
            operationType: GuaranteeOperationCoverageService::OP_BOOKING,
            targetType: GuaranteeLevel::TARGET_CLIENT,
        );

        $this->assertTrue($decision['covered']);
    }
}
