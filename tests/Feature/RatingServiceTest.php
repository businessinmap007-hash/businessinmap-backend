<?php

namespace Tests\Feature;

use App\Models\RatingOutcomeEvent;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\Ratings\RatingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Operation-based rating core: outcome recording (idempotent), both-party
 * recording, and the derived success/cancel/dispute percentages. Rolls back.
 */
class RatingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RatingService $service;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RatingService::class);
        $this->userId = (int) User::query()->orderBy('id')->firstOrFail()->id;
    }

    private function op(): int
    {
        return random_int(700000, 799999);
    }

    public function test_records_success_and_derives_rate(): void
    {
        $base = $this->op();

        // 4 successes, 1 cancel → 80% success, 20% cancel, 0% dispute.
        for ($i = 0; $i < 4; $i++) {
            $this->service->recordOutcome($this->userId, UserOperationRating::ROLE_CLIENT, RatingOutcomeEvent::OUTCOME_SUCCESS, RatingOutcomeEvent::OP_BOOKING, $base + $i);
        }
        $this->service->recordOutcome($this->userId, UserOperationRating::ROLE_CLIENT, RatingOutcomeEvent::OUTCOME_CANCELLED, RatingOutcomeEvent::OP_BOOKING, $base + 99);

        $summary = $this->service->summaryFor($this->userId, UserOperationRating::ROLE_CLIENT);

        $this->assertSame(5, $summary['total_operations']);
        $this->assertSame(4, $summary['success_count']);
        $this->assertSame(1, $summary['cancelled_count']);
        $this->assertSame(80.0, $summary['success_rate']);
        $this->assertSame(20.0, $summary['cancel_rate']);
        $this->assertSame(0.0, $summary['dispute_rate']);
    }

    public function test_recording_is_idempotent_per_operation_and_outcome(): void
    {
        $op = $this->op();

        $first = $this->service->recordOutcome($this->userId, UserOperationRating::ROLE_BUSINESS, RatingOutcomeEvent::OUTCOME_SUCCESS, RatingOutcomeEvent::OP_ORDER, $op);
        $second = $this->service->recordOutcome($this->userId, UserOperationRating::ROLE_BUSINESS, RatingOutcomeEvent::OUTCOME_SUCCESS, RatingOutcomeEvent::OP_ORDER, $op);

        $this->assertTrue($first);
        $this->assertFalse($second, 'A duplicate outcome must be a no-op.');

        $this->assertSame(1, $this->service->summaryFor($this->userId, UserOperationRating::ROLE_BUSINESS)['success_count']);
    }

    public function test_both_parties_are_recorded_in_their_roles(): void
    {
        $business = (int) User::query()->where('type', 'business')->orderBy('id')->firstOrFail()->id;
        $client = (int) User::query()->where('type', 'client')->orderBy('id')->firstOrFail()->id;
        $op = $this->op();

        $this->service->recordForBothParties($business, $client, RatingOutcomeEvent::OUTCOME_SUCCESS, RatingOutcomeEvent::OP_ORDER, $op);

        $this->assertSame(1, $this->service->summaryFor($client, UserOperationRating::ROLE_CLIENT)['success_count']);
        $this->assertSame(1, $this->service->summaryFor($business, UserOperationRating::ROLE_BUSINESS)['success_count']);
    }

    public function test_invalid_role_or_outcome_is_ignored(): void
    {
        $this->assertFalse($this->service->recordOutcome($this->userId, 'admin', RatingOutcomeEvent::OUTCOME_SUCCESS, RatingOutcomeEvent::OP_BOOKING, $this->op()));
        $this->assertFalse($this->service->recordOutcome($this->userId, UserOperationRating::ROLE_CLIENT, 'exploded', RatingOutcomeEvent::OP_BOOKING, $this->op()));
    }

    public function test_summary_is_well_formed_for_a_user_with_no_history(): void
    {
        $summary = $this->service->summaryFor(999999999, UserOperationRating::ROLE_CLIENT);

        $this->assertSame(0, $summary['total_operations']);
        $this->assertSame(0.0, $summary['success_rate']);
    }
}
