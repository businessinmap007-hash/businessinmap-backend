<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\Guarantees\GuaranteeAutoDowngradeService;
use App\Services\Guarantees\GuaranteeGrantService;
use App\Services\Guarantees\GuaranteeUnlockService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Admin-granted guarantees: coverage handed to a business for free, closed so it
 * can never be unlocked back to balance, and exempt from the funding-based
 * downgrade that would otherwise wipe an unbacked level.
 */
class GuaranteeGrantTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $u = new User();
        $u->name = 'Biz ' . Str::random(4);
        $u->email = 'biz-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function businessLevel(): GuaranteeLevel
    {
        return GuaranteeLevel::query()
            ->where('target_type', GuaranteeLevel::TARGET_BUSINESS)
            ->where('active_coverage_amount', '>', 0)
            ->orderByDesc('required_locked_amount')
            ->firstOr(fn () => $this->markTestSkipped('Needs a business guarantee level.'));
    }

    public function test_an_admin_grants_a_free_closed_guarantee(): void
    {
        $business = $this->business();
        $level = $this->businessLevel();

        // Give the business a wallet to prove the grant never touches it.
        app(WalletService::class)->getOrCreateWallet((int) $business->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 500, 'locked_balance' => 0]);

        $g = app(GuaranteeGrantService::class)->grant($business, $level, 999, 'goodwill');

        $this->assertSame(UserGuarantee::STATUS_ACTIVE, $g->status);
        $this->assertTrue((bool) $g->is_granted);
        $this->assertSame(999, (int) $g->granted_by);
        $this->assertEqualsWithDelta(0.0, (float) $g->locked_amount, 0.01);
        $this->assertEqualsWithDelta((float) $level->active_coverage_amount, (float) $g->current_coverage_amount, 0.01);

        // The wallet is untouched — no payment, nothing locked.
        $wallet = Wallet::query()->where('user_id', $business->id)->first();
        $this->assertEqualsWithDelta(500.0, (float) $wallet->balance, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $wallet->locked_balance, 0.01);

        $this->assertDatabaseHas('guarantee_transactions', [
            'user_guarantee_id' => $g->id, 'type' => 'grant',
        ]);
    }

    public function test_a_granted_guarantee_cannot_be_unlocked_to_balance(): void
    {
        $business = $this->business();
        app(GuaranteeGrantService::class)->grant($business, $this->businessLevel(), 999);

        $this->expectException(ValidationException::class);
        app(GuaranteeUnlockService::class)->unlockToBalance($business, GuaranteeLevel::TARGET_BUSINESS);
    }

    public function test_a_coverage_sync_does_not_wipe_a_granted_guarantee(): void
    {
        $business = $this->business();
        $level = $this->businessLevel();
        $g = app(GuaranteeGrantService::class)->grant($business, $level, 999);
        $coverageBefore = (float) $g->current_coverage_amount;

        $result = app(GuaranteeAutoDowngradeService::class)->syncEffectiveLevel($g);

        $this->assertFalse($result['changed']);
        $this->assertSame('granted_exempt', $result['reason']);
        $this->assertEqualsWithDelta(
            $coverageBefore,
            (float) $g->fresh()->current_coverage_amount,
            0.01,
            'the granted coverage survives a funding-based sync'
        );
    }

    public function test_granting_refuses_a_non_business(): void
    {
        $client = new User();
        $client->name = 'Client ' . Str::random(4);
        $client->email = 'cl-' . uniqid() . '@example.test';
        $client->phone = '01' . random_int(100000000, 999999999);
        $client->password = 'secret-password';
        $client->type = User::TYPE_CLIENT;
        $client->api_token = Str::random(80);
        $client->save();

        $this->expectException(ValidationException::class);
        app(GuaranteeGrantService::class)->grant($client, $this->businessLevel(), 999);
    }

    public function test_granting_refuses_to_clobber_a_paid_guarantee(): void
    {
        $business = $this->business();
        $level = $this->businessLevel();

        // An existing PAID guarantee (real locked money).
        UserGuarantee::create([
            'user_id' => $business->id,
            'target_type' => GuaranteeLevel::TARGET_BUSINESS,
            'purchased_level_id' => $level->id,
            'effective_level_id' => $level->id,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'locked_amount' => 1000,
            'current_coverage_amount' => (float) $level->active_coverage_amount,
        ]);

        $this->expectException(ValidationException::class);
        app(GuaranteeGrantService::class)->grant($business, $level, 999);
    }
}
