<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Services\Guarantees\GuaranteeAutoDowngradeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regressions for the guarantee admin flow:
 *  - guarantee_transactions.type accepts the admin/expiration verbs
 *    (was truncated: manual_suspend / manual_reactivate / manual_expiration /
 *    expiration).
 *  - syncEffectiveLevel is idempotent per idempotency_key (re-running the same
 *    downgrade/sync skips the insert instead of hitting the unique constraint).
 */
class GuaranteeTransactionFixesTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;

    private int $guaranteeId;

    private int $levelId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::query()->orderBy('id')->first();
        $this->levelId = (int) DB::table('guarantee_levels')->value('id');

        if (! $user || $this->levelId <= 0) {
            $this->markTestSkipped('Needs a user and a guarantee level.');
        }

        $this->userId = (int) $user->id;

        UserGuarantee::query()->where('user_id', $this->userId)->where('target_type', 'client')->delete();
        $this->guaranteeId = (int) UserGuarantee::create([
            'user_id' => $this->userId,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $this->levelId,
            'effective_level_id' => null,
            'status' => UserGuarantee::STATUS_PENDING_OPERATIONS,
            'current_coverage_amount' => 0,
            'used_coverage_amount' => 0,
            'locked_amount' => 999999,
            'completed_operations_count' => 999999,
            'trust_score' => 999999,
            'disputes_lost_count' => 0,
            'late_cancellations_count' => 0,
        ])->id;
    }

    public function test_type_enum_accepts_admin_and_expiration_verbs(): void
    {
        foreach (['manual_suspend', 'manual_reactivate', 'manual_expiration', 'expiration'] as $i => $type) {
            $tx = GuaranteeTransaction::create([
                'user_id' => $this->userId,
                'user_guarantee_id' => $this->guaranteeId,
                'type' => $type,
                'amount' => 0,
                'coverage_amount' => 0,
                'reference_type' => 'admin_action',
                'reference_id' => $this->guaranteeId,
                'reason' => 'test',
                'idempotency_key' => 'test_type_' . $type . '_' . uniqid(),
                'meta' => [],
            ]);

            $this->assertSame($type, (string) $tx->fresh()->type, "type '{$type}' must persist without truncation");
        }
    }

    public function test_sync_effective_level_is_idempotent_on_repeat_key(): void
    {
        $service = app(GuaranteeAutoDowngradeService::class);
        $key = 'guarantee_downgrade:test:' . uniqid();

        // A prior run already logged this operation.
        GuaranteeTransaction::create([
            'user_id' => $this->userId,
            'user_guarantee_id' => $this->guaranteeId,
            'type' => 'upgrade',
            'amount' => 0,
            'coverage_amount' => 0,
            'reference_type' => 'admin_action',
            'reference_id' => $this->guaranteeId,
            'reason' => 'prior',
            'idempotency_key' => $key,
            'meta' => [],
        ]);

        $guarantee = UserGuarantee::query()->findOrFail($this->guaranteeId);

        // Re-running the sync with the SAME key must not throw a duplicate-key
        // error; it skips the insert. (Guarantee still syncs its level.)
        $result = $service->syncEffectiveLevel($guarantee, 'admin_action', $this->guaranteeId, ['idempotency_key' => $key]);

        $this->assertTrue((bool) ($result['changed'] ?? false), 'the level sync still applies');
        $this->assertSame(
            1,
            GuaranteeTransaction::query()->where('idempotency_key', $key)->count(),
            'no duplicate transaction for the same idempotency key'
        );
        $this->assertSame(
            $this->levelId,
            (int) UserGuarantee::query()->whereKey($this->guaranteeId)->value('effective_level_id'),
            'the effective level was synced'
        );
    }
}
