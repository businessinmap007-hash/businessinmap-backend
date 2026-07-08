<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Services\Guarantees\OperationGuarantorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Friend co-guarantor for one operation (guarantee-as-deposit). The requester's
 * own coverage is supplemented by a friend's platform-purchased guarantee for a
 * single operation: accepting FREEZES the friend's coverage (never charges it),
 * releasing returns it. All rows are created inside a rolled-back transaction.
 */
class OperationGuarantorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OperationGuarantorService $svc;

    private User $requester;

    private User $friend;

    private int $opId = 990001;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(OperationGuarantorService::class);

        $users = User::query()->orderBy('id')->take(2)->get();
        $levelId = (int) DB::table('guarantee_levels')->value('id');

        if ($users->count() < 2 || $levelId <= 0) {
            $this->markTestSkipped('Needs two users and a guarantee level.');
        }

        $this->requester = $users[0];
        $this->friend = $users[1];

        // Fresh client guarantees: each covers 1500 (own), nothing used.
        foreach ([$this->requester->id, $this->friend->id] as $uid) {
            UserGuarantee::query()->where('user_id', $uid)->where('target_type', 'client')->delete();
            UserGuarantee::create([
                'user_id' => $uid,
                'target_type' => GuaranteeLevel::TARGET_CLIENT,
                'purchased_level_id' => $levelId,
                'effective_level_id' => $levelId,
                'status' => UserGuarantee::STATUS_ACTIVE,
                'current_coverage_amount' => 1500,
                'used_coverage_amount' => 0,
            ]);
        }

        OperationGuarantor::query()->forOperation('booking', $this->opId)->delete();
    }

    private function friendGuarantee(): UserGuarantee
    {
        return UserGuarantee::query()->where('user_id', $this->friend->id)->where('target_type', 'client')->first();
    }

    public function test_invite_creates_a_pending_row_and_is_reused(): void
    {
        $first = $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);
        $second = $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);

        $this->assertSame($first->id, $second->id, 'a pending invite is reused, not duplicated');
        $this->assertSame(OperationGuarantor::STATUS_INVITED, $first->status);
        $this->assertSame((int) $this->friendGuarantee()->id, (int) $first->user_guarantee_id);
    }

    public function test_friend_coverage_supplements_the_requester_to_cover_the_operation(): void
    {
        // Own 1500 is not enough for a 3000 operation.
        $this->assertFalse($this->svc->isOperationCovered('booking', $this->opId, $this->requester, 3000));

        $row = $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);
        $this->svc->accept($row, 1500);

        // 1500 (own) + 1500 (friend, frozen) = 3000 → covered.
        $this->assertEqualsWithDelta(3000.0, $this->svc->combinedCoverage('booking', $this->opId, $this->requester), 0.001);
        $this->assertTrue($this->svc->isOperationCovered('booking', $this->opId, $this->requester, 3000));

        // Friend's coverage is frozen, not charged.
        $g = $this->friendGuarantee();
        $this->assertEqualsWithDelta(1500.0, (float) $g->used_coverage_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, $g->availableCoverage(), 0.001);
    }

    public function test_release_returns_the_friends_frozen_coverage(): void
    {
        $row = $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);
        $this->svc->accept($row, 1500);

        $this->svc->releaseOperation('booking', $this->opId);

        $g = $this->friendGuarantee();
        $this->assertEqualsWithDelta(0.0, (float) $g->used_coverage_amount, 0.001, 'coverage returned in full');
        $this->assertEqualsWithDelta(1500.0, $g->availableCoverage(), 0.001);
        $this->assertSame(
            OperationGuarantor::STATUS_RELEASED,
            OperationGuarantor::query()->whereKey($row->id)->value('status')
        );
    }

    public function test_accept_is_idempotent(): void
    {
        $row = $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);
        $this->svc->accept($row, 1500);
        $this->svc->accept($row, 1500);

        $this->assertEqualsWithDelta(1500.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'coverage frozen only once');
    }

    public function test_accept_beyond_friend_coverage_is_rejected(): void
    {
        $row = $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);

        $this->expectException(ValidationException::class);
        $this->svc->accept($row, 2000); // friend only has 1500
    }

    public function test_cannot_guarantee_yourself(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->invite('booking', $this->opId, $this->requester, $this->requester);
    }

    public function test_inviting_a_friend_without_a_guarantee_is_rejected(): void
    {
        UserGuarantee::query()->where('user_id', $this->friend->id)->where('target_type', 'client')->delete();

        $this->expectException(ValidationException::class);
        $this->svc->invite('booking', $this->opId, $this->requester, $this->friend);
    }

    public function test_self_freeze_locks_only_the_operation_amount_not_the_whole_guarantee(): void
    {
        // Requester's guarantee covers 1500. A 200 operation must freeze only
        // 200, leaving 1300 available for other operations within coverage.
        $this->svc->freezeSelf('booking', $this->opId, $this->requester, 200);

        $g = UserGuarantee::query()->where('user_id', $this->requester->id)->where('target_type', 'client')->first();
        $this->assertEqualsWithDelta(200.0, (float) $g->used_coverage_amount, 0.001, 'only the operation amount is frozen');
        $this->assertEqualsWithDelta(1300.0, $g->availableCoverage(), 0.001, 'the rest stays available for other operations');
    }
}
