<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountDeletionService;
use App\Services\WalletService;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The admin screen for deletions the day-31 sweep refused (BIM-15.1).
 *
 * The state under test is reached the real way — request a deletion, put money
 * in the locked balance, run finalize() — rather than by writing a hold reason
 * onto a row by hand. A hand-made row would prove the screen renders a column;
 * this proves the screen reaches the accounts the sweep actually strands, since
 * dueForFinalization() skips them forever after.
 */
class AdminHeldDeletionsTest extends TestCase
{
    use DatabaseTransactions;

    private AccountDeletionService $deletion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deletion = app(AccountDeletionService::class);
    }

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');
    }

    private function makeUser(float $balance = 0, float $locked = 0): User
    {
        $user = new User();
        $user->name = 'Held Deletion Test';
        $user->email = 'held-' . uniqid() . '@example.test';
        $user->phone = '0100' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = User::TYPE_CLIENT;
        $user->api_token = Str::random(80);
        $user->save();

        app(WalletService::class)->getOrCreateWallet((int) $user->id)->update([
            'status' => Wallet::STATUS_ACTIVE,
            'balance' => $balance,
            'locked_balance' => $locked,
        ]);

        return $user->fresh();
    }

    /** An account the sweep refused: requested, then found holding locked money. */
    private function heldAccount(float $locked = 250.0): User
    {
        $user = $this->makeUser(balance: 100.0);

        $this->deletion->request($user);

        // The lock lands after the request — exactly the case finalize() refuses
        // to decide on its own.
        Wallet::query()->where('user_id', $user->id)->update(['locked_balance' => $locked]);

        $result = $this->deletion->finalize($user->fresh() ?? $user);
        $this->assertSame('held', $result['status'], 'setup must produce a held account');

        return User::onlyTrashed()->findOrFail($user->id);
    }

    public function test_a_held_account_is_invisible_to_the_sweep_but_listed_on_the_screen(): void
    {
        $user = $this->heldAccount();

        // The gap this screen closes: nothing brings a held row back on its own.
        $this->assertFalse(
            $this->deletion->dueForFinalization()->contains('id', $user->id),
            'a held account must stay out of the automated sweep'
        );

        $this->actingAs($this->admin())
            ->get('/admin/held-deletions')
            ->assertOk()
            ->assertSee((string) $user->id, false);
    }

    public function test_the_screen_says_whether_the_hold_still_applies(): void
    {
        $user = $this->heldAccount();

        $this->actingAs($this->admin())
            ->get('/admin/held-deletions')
            ->assertOk()
            ->assertSee(__('ما زال قائمًا'), false);

        // Resolve the underlying cause; the stored reason is now just a snapshot.
        Wallet::query()->where('user_id', $user->id)->update(['locked_balance' => 0]);

        $this->actingAs($this->admin())
            ->get('/admin/held-deletions')
            ->assertOk()
            ->assertSee(__('زال السبب'), false);
    }

    /** Retrying while the cause stands must re-hold, never force the deletion. */
    public function test_finalizing_a_still_blocked_account_is_refused(): void
    {
        $user = $this->heldAccount();

        $this->actingAs($this->admin())
            ->post("/admin/held-deletions/{$user->id}/finalize")
            ->assertRedirect();

        $after = User::onlyTrashed()->findOrFail($user->id);

        $this->assertNull($after->anonymized_at, 'contested money must never be seized by a retry');
        $this->assertNotNull($after->deletion_hold_reason, 'the account must stay held');
    }

    public function test_finalizing_after_the_cause_is_resolved_completes_the_deletion(): void
    {
        $user = $this->heldAccount();

        Wallet::query()->where('user_id', $user->id)->update(['locked_balance' => 0]);

        $this->actingAs($this->admin())
            ->post("/admin/held-deletions/{$user->id}/finalize")
            ->assertRedirect();

        $after = User::onlyTrashed()->findOrFail($user->id);

        $this->assertNotNull($after->anonymized_at, 'the deletion should now be finished');
    }

    public function test_restoring_gives_the_account_and_its_wallet_back(): void
    {
        $user = $this->heldAccount();

        $this->actingAs($this->admin())
            ->post("/admin/held-deletions/{$user->id}/restore")
            ->assertRedirect();

        $after = User::query()->find($user->id);

        $this->assertNotNull($after, 'the soft delete must be undone');
        $this->assertNull($after->deletion_requested_at);
        $this->assertNull($after->deletion_hold_reason);
        $this->assertSame(
            Wallet::STATUS_ACTIVE,
            Wallet::query()->where('user_id', $user->id)->value('status'),
            'the wallet freeze must be lifted'
        );
    }

    /** An account that is not held is not actionable from this screen. */
    public function test_a_user_that_is_not_held_is_not_found(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->admin())
            ->post("/admin/held-deletions/{$user->id}/finalize")
            ->assertNotFound();
    }

    /** Both actions move money, so the screen sits behind the money ability. */
    public function test_the_screen_requires_the_money_ability(): void
    {
        $admin = new User();
        $admin->name = 'Held Deletions Ability Test';
        $admin->email = 'held-ability-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        // Into the panel, but without the money ability.
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::refresh();

        $this->actingAs($admin)->get('/admin/held-deletions')->assertForbidden();
    }
}
