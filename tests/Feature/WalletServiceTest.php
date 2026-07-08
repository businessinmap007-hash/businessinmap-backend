<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Wallet ledger guards (Phase: financial-core tests). Runs against the real dev
 * DB inside a rolled-back transaction; the wallet is normalised to active at
 * the start of each test and assertions use balance DELTAS so they don't depend
 * on the user's starting balance.
 */
class WalletServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WalletService $wallet;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallet = app(WalletService::class);

        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            $this->markTestSkipped('Needs a user.');
        }
        $this->userId = (int) $user->id;

        // Normalise wallet state (rolled back after the test).
        $w = $this->wallet->getOrCreateWallet($this->userId);
        $w->update(['status' => Wallet::STATUS_ACTIVE]);
    }

    private function fresh(): Wallet
    {
        return $this->wallet->getOrCreateWallet($this->userId)->refresh();
    }

    public function test_deposit_increases_available_balance(): void
    {
        $before = $this->fresh()->availableBalance();

        $this->wallet->deposit($this->userId, 100, 'test deposit');

        $this->assertEqualsWithDelta($before + 100, $this->fresh()->availableBalance(), 0.001);
    }

    public function test_deposit_is_idempotent(): void
    {
        $before = $this->fresh()->availableBalance();
        $key = 'test-idem-' . uniqid();

        $tx1 = $this->wallet->deposit($this->userId, 100, null, 'test', '1', $key);
        $tx2 = $this->wallet->deposit($this->userId, 100, null, 'test', '1', $key);

        $this->assertSame($tx1->id, $tx2->id, 'same idempotency key must return the same transaction');
        $this->assertEqualsWithDelta($before + 100, $this->fresh()->availableBalance(), 0.001, 'must credit only once');
    }

    public function test_withdraw_beyond_balance_is_rejected(): void
    {
        $available = $this->fresh()->availableBalance();

        try {
            $this->wallet->withdraw($this->userId, $available + 1_000_000, 'overdraw');
            $this->fail('withdrawing more than the balance must throw');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('balance', $e->errors());
        }

        $this->assertEqualsWithDelta($available, $this->fresh()->availableBalance(), 0.001, 'balance must be unchanged after a rejected withdraw');
    }

    public function test_hold_moves_available_to_locked_and_release_reverses_it(): void
    {
        // Fund enough headroom first.
        $this->wallet->deposit($this->userId, 100, 'fund');

        $avail0 = $this->fresh()->availableBalance();
        $locked0 = $this->fresh()->lockedBalance();
        $total0 = $this->fresh()->totalBalance();

        $this->wallet->hold($this->userId, 40, 'booking', 'test-ref');

        $this->assertEqualsWithDelta($avail0 - 40, $this->fresh()->availableBalance(), 0.001);
        $this->assertEqualsWithDelta($locked0 + 40, $this->fresh()->lockedBalance(), 0.001);
        $this->assertEqualsWithDelta($total0, $this->fresh()->totalBalance(), 0.001, 'a hold moves funds, never creates or destroys them');

        $this->wallet->release($this->userId, 40, 'booking', 'test-ref');

        $this->assertEqualsWithDelta($avail0, $this->fresh()->availableBalance(), 0.001);
        $this->assertEqualsWithDelta($locked0, $this->fresh()->lockedBalance(), 0.001);
    }
}
