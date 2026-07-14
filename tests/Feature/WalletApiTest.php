<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPin;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * v2 wallet API: balance, ledger, money movements, and the PIN gate on
 * withdraw/transfer. Rolls back (DatabaseTransactions).
 */
class WalletApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->orderBy('id')->firstOrFail();
        app(WalletService::class)->getOrCreateWallet((int) $this->user->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 100, 'locked_balance' => 0]);
        WalletPin::where('user_id', $this->user->id)->delete();
    }

    public function test_show_returns_balance(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/wallet')->assertOk();
        $this->assertEquals(100.0, $res->json('data.balance'));
    }

    public function test_deposit_increases_balance_and_is_idempotent(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', 'dep-key-1')
            ->postJson('/api/v2/wallet/deposit', ['amount' => 25])
            ->assertCreated();

        $this->assertSame(125.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));

        // Same key → no second credit.
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', 'dep-key-1')
            ->postJson('/api/v2/wallet/deposit', ['amount' => 25])
            ->assertCreated();

        $this->assertSame(125.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));
    }

    public function test_set_pin_then_withdraw_respects_pin(): void
    {
        // Set a PIN (confirmed).
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertOk();

        // Wrong PIN → rejected, no debit.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/withdraw', ['amount' => 10, 'pin' => '9999'])
            ->assertStatus(422);
        $this->assertSame(100.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));

        // Correct PIN → debit.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/withdraw', ['amount' => 10, 'pin' => '1234'])
            ->assertCreated();
        $this->assertSame(90.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));
    }

    public function test_transfer_moves_funds_and_blocks_self(): void
    {
        $recipient = User::query()->where('id', '!=', $this->user->id)->orderBy('id')->firstOrFail();
        app(WalletService::class)->getOrCreateWallet((int) $recipient->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 0]);
        app(WalletService::class)->setPin((int) $this->user->id, '4321');

        // Self-transfer blocked.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/transfer', ['to_user_id' => $this->user->id, 'amount' => 5, 'pin' => '4321'])
            ->assertStatus(422);

        // Valid transfer.
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/wallet/transfer', ['to_user_id' => $recipient->id, 'amount' => 30, 'pin' => '4321'])
            ->assertCreated();

        $this->assertSame(70.0, (float) Wallet::where('user_id', $this->user->id)->value('balance'));
        $this->assertSame(30.0, (float) Wallet::where('user_id', $recipient->id)->value('balance'));
    }

    public function test_wallet_requires_auth(): void
    {
        $this->getJson('/api/v2/wallet')->assertUnauthorized();
    }
}
