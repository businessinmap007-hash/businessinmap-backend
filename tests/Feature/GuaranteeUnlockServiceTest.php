<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\Guarantees\GuaranteeUnlockService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unlocking a guarantee returns its backing money (locked_amount) from the
 * wallet's locked_balance to the free balance — reversing activation — but only
 * when none of its coverage is reserved for an active operation.
 */
class GuaranteeUnlockServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GuaranteeUnlockService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GuaranteeUnlockService::class);

        $user = User::query()->orderBy('id')->first();
        $levelId = (int) DB::table('guarantee_levels')->value('id');
        if (! $user || $levelId <= 0) {
            $this->markTestSkipped('Needs a user and a guarantee level.');
        }
        $this->user = $user;

        // Active guarantee backed by 1000 held in the wallet's locked_balance.
        UserGuarantee::query()->where('user_id', $user->id)->where('target_type', 'client')->delete();
        UserGuarantee::create([
            'user_id' => $user->id,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $levelId,
            'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'locked_amount' => 1000,
            'current_coverage_amount' => 1500,
            'used_coverage_amount' => 0,
        ]);

        OperationGuarantor::query()->where('guarantor_user_id', $user->id)->delete();

        $w = app(WalletService::class)->getOrCreateWallet((int) $user->id);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 200, 'locked_balance' => 1000]);
    }

    private function guarantee(): UserGuarantee
    {
        return UserGuarantee::query()->where('user_id', $this->user->id)->where('target_type', 'client')->latest('id')->first();
    }

    public function test_unlock_returns_backing_money_to_balance_and_cancels_the_guarantee(): void
    {
        $result = $this->service->unlockToBalance($this->user);

        $this->assertEqualsWithDelta(1000.0, (float) $result['amount'], 0.001);

        $w = Wallet::query()->where('user_id', $this->user->id)->first();
        $this->assertEqualsWithDelta(1200.0, (float) $w->balance, 0.001, 'backing money moved to free balance');
        $this->assertEqualsWithDelta(0.0, (float) $w->locked_balance, 0.001, 'guarantee lock released from the wallet');

        $g = $this->guarantee();
        $this->assertSame(UserGuarantee::STATUS_CANCELLED, (string) $g->status);
        $this->assertEqualsWithDelta(0.0, (float) $g->locked_amount, 0.001);

        $this->assertTrue(
            GuaranteeTransaction::query()->where('user_guarantee_id', $g->id)->where('type', 'unlock')->exists()
        );
    }

    public function test_cannot_unlock_when_coverage_is_reserved(): void
    {
        UserGuarantee::query()->where('user_id', $this->user->id)->where('target_type', 'client')
            ->update(['used_coverage_amount' => 500]);

        try {
            $this->service->unlockToBalance($this->user);
            $this->fail('unlocking reserved coverage must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guarantee', $e->errors());
        }

        $w = Wallet::query()->where('user_id', $this->user->id)->first();
        $this->assertEqualsWithDelta(200.0, (float) $w->balance, 0.001, 'wallet untouched on rejection');
        $this->assertEqualsWithDelta(1000.0, (float) $w->locked_balance, 0.001);
    }

    public function test_cannot_unlock_while_co_guaranteeing_a_friend(): void
    {
        OperationGuarantor::create([
            'operation_type' => 'booking',
            'operation_id' => 987654,
            'requester_user_id' => (int) $this->user->id,
            'guarantor_user_id' => $this->user->id,
            'user_guarantee_id' => $this->guarantee()->id,
            'covered_amount' => 300,
            'status' => OperationGuarantor::STATUS_ACCEPTED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->unlockToBalance($this->user);
    }
}
