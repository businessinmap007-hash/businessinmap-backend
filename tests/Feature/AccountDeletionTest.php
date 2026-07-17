<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AccountDeletionService;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BIM-15.1 — account deletion.
 *
 * The properties worth protecting: money never moves on day 0 (that is what
 * makes restore possible), money is conserved when it finally does move, and a
 * ban is not erased by deleting the account that carries it. Rolls back.
 */
class AccountDeletionTest extends TestCase
{
    use DatabaseTransactions;

    private AccountDeletionService $deletion;
    private PlatformTreasuryService $treasury;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deletion = app(AccountDeletionService::class);
        $this->treasury = app(PlatformTreasuryService::class);
    }

    private function makeUser(float $balance = 0, float $locked = 0): User
    {
        $user = new User();
        $user->name = 'Deletion Test';
        $user->email = 'del-test-' . uniqid() . '@example.test';
        $user->phone = '0100' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = User::TYPE_CLIENT;
        $user->api_token = Str::random(80);
        $user->save();

        $wallet = app(WalletService::class)->getOrCreateWallet((int) $user->id);
        $wallet->update([
            'status' => Wallet::STATUS_ACTIVE,
            'balance' => $balance,
            'locked_balance' => $locked,
        ]);

        return $user->fresh();
    }

    private function balanceOf(int $userId): float
    {
        return round((float) Wallet::query()->where('user_id', $userId)->value('balance'), 2);
    }

    private function blockerCodes(User $user): array
    {
        return array_column($this->deletion->blockers($user), 'code');
    }

    // ------------------------------------------------------------- blockers

    public function test_a_clean_account_can_be_deleted(): void
    {
        $this->assertTrue($this->deletion->canDelete($this->makeUser(100)));
    }

    public function test_a_pending_operation_on_either_side_blocks_deletion(): void
    {
        $client = $this->makeUser();
        $business = $this->makeUser();

        $booking = Booking::query()->create([
            'user_id' => $client->id,
            'business_id' => $business->id,
            'status' => Booking::STATUS_PENDING,
            'date' => now()->addDay()->toDateString(),
            'time' => '12:00:00',
        ]);

        // Both parties are stuck: deleting either one strands the other.
        $this->assertContains('pending_bookings', $this->blockerCodes($client));
        $this->assertContains('pending_bookings', $this->blockerCodes($business));

        $booking->update(['status' => Booking::STATUS_COMPLETED]);

        $this->assertNotContains('pending_bookings', $this->blockerCodes($client->fresh()));
    }

    public function test_an_open_dispute_blocks_deletion_for_both_parties(): void
    {
        $opener = $this->makeUser();
        $against = $this->makeUser();

        Dispute::query()->create([
            'disputeable_type' => Booking::class,
            'disputeable_id' => 1,
            'type' => 'booking',
            'opened_by_user_id' => $opener->id,
            'against_user_id' => $against->id,
            'status' => Dispute::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $this->assertContains('open_dispute', $this->blockerCodes($opener));
        $this->assertContains('open_dispute', $this->blockerCodes($against));
    }

    public function test_escrow_still_held_blocks_deletion(): void
    {
        // Locked balance is a deposit against a live operation — not the account
        // holder's money to walk away from.
        $this->assertContains('locked_balance', $this->blockerCodes($this->makeUser(50, 200)));
    }

    public function test_a_banned_account_cannot_delete_itself(): void
    {
        // Otherwise deletion is the undo button for a permanent ban: finalize()
        // scrubs the very email and phone the ban is enforced on.
        $user = $this->makeUser();
        $user->banned_at = now();
        $user->ban_reason = 'fraud';
        $user->save();

        $this->assertContains('banned', $this->blockerCodes($user));
    }

    public function test_the_platform_treasury_cannot_be_deleted(): void
    {
        if (! $this->treasury->isConfigured()) {
            $this->markTestSkipped('Needs BIM_PLATFORM_WALLET_USER_ID.');
        }

        $this->assertContains('platform_account', $this->blockerCodes($this->treasury->account()));
    }

    // ----------------------------------------------------- request / restore

    public function test_requesting_deletion_freezes_the_wallet_but_moves_no_money(): void
    {
        $user = $this->makeUser(250);
        $user->createToken('mobile');

        $this->deletion->request($user);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(250.0, $this->balanceOf((int) $user->id), 'the balance must NOT move on day 0 — that is what makes restore possible');
        $this->assertSame(Wallet::STATUS_BLOCKED, Wallet::query()->where('user_id', $user->id)->value('status'));
        $this->assertSame(0, $user->tokens()->count(), 'every device is logged out');
        $this->assertNotNull($user->fresh()->deletion_scheduled_at);
    }

    public function test_the_freeze_is_real_and_not_just_a_column(): void
    {
        $user = $this->makeUser(250);
        $this->deletion->request($user);

        // WalletService::ensureActive() is what makes the freeze mean anything.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(WalletService::class)->withdraw((int) $user->id, 10);
    }

    public function test_restoring_within_the_window_returns_the_account_and_the_balance(): void
    {
        $user = $this->makeUser(250);
        $this->deletion->request($user);

        $this->deletion->restore($user);

        $restored = User::query()->find($user->id);
        $this->assertNotNull($restored, 'the account is back');
        $this->assertNull($restored->deletion_requested_at);
        $this->assertSame(250.0, $this->balanceOf((int) $user->id), 'and so is the money');
        $this->assertSame(Wallet::STATUS_ACTIVE, Wallet::query()->where('user_id', $user->id)->value('status'));
    }

    public function test_deletion_is_refused_while_blocked(): void
    {
        $user = $this->makeUser(10, 100); // escrow held

        $this->expectException(\RuntimeException::class);
        $this->deletion->request($user);
    }

    // -------------------------------------------------------------- finalize

    public function test_the_sweep_only_picks_up_accounts_past_the_grace_window(): void
    {
        $user = $this->makeUser(10);
        $this->deletion->request($user);

        $this->assertFalse(
            $this->deletion->dueForFinalization()->contains('id', $user->id),
            'still inside the window'
        );

        $user->deletion_scheduled_at = now()->subDay();
        $user->save();

        $this->assertTrue($this->deletion->dueForFinalization()->contains('id', $user->id));
    }

    public function test_finalizing_escheats_the_balance_and_conserves_money(): void
    {
        if (! $this->treasury->isConfigured()) {
            $this->markTestSkipped('Needs BIM_PLATFORM_WALLET_USER_ID.');
        }

        $user = $this->makeUser(180);
        $treasuryId = (int) $this->treasury->accountId();
        $platformBefore = $this->balanceOf($treasuryId);

        $this->deletion->request($user);
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();

        $result = $this->deletion->finalize($user);

        $this->assertSame('finalized', $result['status']);
        $this->assertSame(180.0, $result['escheated']);
        $this->assertSame(0.0, $this->balanceOf((int) $user->id));
        $this->assertSame(round($platformBefore + 180, 2), $this->balanceOf($treasuryId));

        // Escheat is money the platform holds, not money it earned.
        $credit = WalletTransaction::query()
            ->where('user_id', $treasuryId)
            ->where('reference_type', PlatformTreasuryService::PURPOSE_ESCHEAT)
            ->where('reference_id', (string) $user->id)
            ->first();

        $this->assertNotNull($credit);
        $this->assertSame(180.0, round((float) $credit->amount, 2));
    }

    public function test_finalizing_anonymizes_the_identity_but_keeps_the_row_and_the_join_date(): void
    {
        $user = $this->makeUser(20);
        $originalEmail = $user->email;
        $createdAt = $user->created_at;

        $this->deletion->request($user);
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();
        $this->deletion->finalize($user);

        $row = User::withTrashed()->find($user->id);

        $this->assertNotNull($row, 'the row survives — other people\'s ledger, ratings and invoices point at this id');
        $this->assertNotSame($originalEmail, $row->email);
        $this->assertStringEndsWith('@deleted.invalid', $row->email);
        $this->assertSame('حساب محذوف', $row->name);
        $this->assertNull($row->about);
        $this->assertNotNull($row->anonymized_at);
        $this->assertEquals($createdAt, $row->created_at, 'the registration date is kept on purpose');
    }

    public function test_the_sweep_refuses_to_seize_contested_money(): void
    {
        $user = $this->makeUser(500);
        $this->deletion->request($user);
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();

        // A dispute appeared after the request — an unattended job must not be
        // the thing that decides who this money belongs to.
        Dispute::query()->create([
            'disputeable_type' => Booking::class,
            'disputeable_id' => 1,
            'type' => 'booking',
            'opened_by_user_id' => $this->makeUser()->id,
            'against_user_id' => $user->id,
            'status' => Dispute::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $result = $this->deletion->finalize($user);

        $this->assertSame('held', $result['status']);
        $this->assertSame(500.0, $this->balanceOf((int) $user->id), 'the money is untouched');
        $this->assertNull(User::withTrashed()->find($user->id)->anonymized_at, 'and the identity is not scrubbed');
        $this->assertFalse($this->deletion->dueForFinalization()->contains('id', $user->id), 'a held account waits for a human, not for the next sweep');
    }

    public function test_finalizing_twice_escheats_once(): void
    {
        if (! $this->treasury->isConfigured()) {
            $this->markTestSkipped('Needs BIM_PLATFORM_WALLET_USER_ID.');
        }

        $user = $this->makeUser(90);
        $treasuryId = (int) $this->treasury->accountId();
        $before = $this->balanceOf($treasuryId);

        $this->deletion->request($user);
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();

        $this->deletion->finalize($user);
        $second = $this->deletion->finalize($user->fresh());

        $this->assertSame('already_finalized', $second['status']);
        $this->assertSame(round($before + 90, 2), $this->balanceOf($treasuryId));
    }

    public function test_an_anonymized_account_cannot_be_restored(): void
    {
        $user = $this->makeUser(10);
        $this->deletion->request($user);
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();
        $this->deletion->finalize($user);

        $this->expectException(\RuntimeException::class);
        $this->deletion->restore($user->fresh());
    }

    // ------------------------------------------------------------- the ban

    public function test_a_ban_survives_the_anonymization_that_erases_the_identity(): void
    {
        // The whole reason blocked_identities exists: finalize() destroys the
        // email and phone the ban is enforced on. Without the hashed list,
        // delete → re-register would clear a permanent ban.
        $user = $this->makeUser(0);
        $email = $user->email;
        $phone = $user->phone;

        $user->banned_at = now();
        $user->ban_reason = 'fake operations';
        $user->save();

        // A banned account cannot delete itself, so this is the admin path:
        // the ban is lifted from the blockers only by the sweep's own hand.
        $user->deletion_requested_at = now();
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();
        $user->delete();

        $this->deletion->finalize($user);

        $this->assertNotSame($email, User::withTrashed()->find($user->id)->email, 'the identity is gone from users');
        $this->assertTrue(BlockedIdentity::isBlocked($email, $phone), 'but the ban still recognises it');
    }

    public function test_an_ordinary_user_who_leaves_is_free_to_come_back(): void
    {
        $user = $this->makeUser(0);
        $email = $user->email;
        $phone = $user->phone;

        $this->deletion->request($user);
        $user->deletion_scheduled_at = now()->subDay();
        $user->save();
        $this->deletion->finalize($user);

        $this->assertFalse(BlockedIdentity::isBlocked($email, $phone), 'leaving is not a punishment');
    }
}
