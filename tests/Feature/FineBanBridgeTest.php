<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\Fine;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Stage B: levying a fine can also ban the account.
 *
 * Fraud rarely deserves only a fine. The bridge reuses the standalone ban
 * wholesale, and the key invariant is that the two stay independent: the ban
 * stops the account, but the fine's hold still stands, so a successful appeal
 * can still undo the money even after the account is banned.
 */
class FineBanBridgeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->where('type', 'admin')->first()
            ?? User::query()->orderBy('id')->firstOrFail();

        $this->target = new User();
        $this->target->name = 'Fraud Target';
        $this->target->email = 'bridge-' . uniqid() . '@example.test';
        $this->target->phone = '0100' . random_int(1000000, 9999999);
        $this->target->password = 'secret-password';
        $this->target->type = User::TYPE_CLIENT;
        $this->target->api_token = Str::random(80);
        $this->target->save();

        app(WalletService::class)->getOrCreateWallet((int) $this->target->id)->update([
            'status' => Wallet::STATUS_ACTIVE, 'balance' => 500, 'locked_balance' => 0,
        ]);
    }

    public function test_levy_with_also_ban_fines_and_bans(): void
    {
        $this->actingAs($this->admin)->post(route('admin.fines.store'), [
            'user_id' => $this->target->id,
            'amount' => 100,
            'reason' => 'احتيال متكرر',
            'also_ban' => '1',
        ])->assertRedirect();

        // The fine exists and froze money.
        $fine = Fine::query()->where('user_id', $this->target->id)->latest('id')->first();
        $this->assertNotNull($fine);
        $this->assertSame(Fine::STATUS_FROZEN, $fine->status);
        $this->assertEqualsWithDelta(100, (float) $fine->frozen_amount, 0.001);

        // And the account is banned + on the block list.
        $this->assertTrue($this->target->fresh()->isBanned());
        $this->assertTrue(BlockedIdentity::isBlocked($this->target->email, $this->target->phone));
    }

    public function test_levy_without_also_ban_does_not_ban(): void
    {
        $this->actingAs($this->admin)->post(route('admin.fines.store'), [
            'user_id' => $this->target->id,
            'amount' => 40,
            'reason' => 'مخالفة بسيطة',
        ])->assertRedirect();

        $this->assertFalse($this->target->fresh()->isBanned());
    }

    public function test_the_freeze_survives_the_ban_so_the_money_is_still_appealable(): void
    {
        $this->actingAs($this->admin)->post(route('admin.fines.store'), [
            'user_id' => $this->target->id,
            'amount' => 120,
            'reason' => 'احتيال',
            'also_ban' => '1',
        ])->assertRedirect();

        $fine = Fine::query()->where('user_id', $this->target->id)->latest('id')->firstOrFail();

        // Banned, but the fine is still frozen (not collected) — appealable.
        $this->assertTrue($this->target->fresh()->isBanned());
        $this->assertSame(Fine::STATUS_FROZEN, $fine->status);
        $this->assertTrue($fine->is_appealable);
        $wallet = Wallet::where('user_id', $this->target->id)->first();
        $this->assertEqualsWithDelta(120, (float) $wallet->locked_balance, 0.001);
    }
}
