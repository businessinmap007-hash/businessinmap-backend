<?php

namespace Tests\Feature;

use App\Models\Fine;
use App\Models\FineAppeal;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FineService;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Platform fines: freeze → appeal window → deduct, never instant seizure.
 *
 * The tests press on the money rules that make it defensible: levy only freezes
 * (nothing leaves the wallet), an accepted appeal or a cancel gives it all back,
 * a rejected appeal or a closed window captures it to the treasury, and a broke
 * user is still fined with the shortfall topped up before capture. Consent-born
 * fines can't be appealed.
 */
class FineSystemTest extends TestCase
{
    use DatabaseTransactions;

    private FineService $fines;
    private WalletService $wallets;
    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fines = app(FineService::class);
        $this->wallets = app(WalletService::class);

        $this->user = User::query()->orderBy('id')->firstOrFail();
        $this->admin = $this->makeAdmin();

        // A clean, funded wallet to freeze against.
        $this->wallets->getOrCreateWallet((int) $this->user->id)->update([
            'status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0,
        ]);

        // Treasury must exist so a capture has somewhere to land.
        config(['bim.platform_wallet_user_id' => (int) $this->admin->id]);
    }

    private function makeAdmin(): User
    {
        $admin = new User();
        $admin->name = 'Fine Admin';
        $admin->email = 'fine-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = \Illuminate\Support\Str::random(80);
        $admin->save();

        return $admin;
    }

    private function wallet(): Wallet
    {
        return $this->wallets->getOrCreateWallet((int) $this->user->id)->fresh();
    }

    public function test_levy_freezes_but_does_not_deduct(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 150, 'احتيال', (int) $this->admin->id);

        $this->assertSame(Fine::STATUS_FROZEN, $fine->status);
        $this->assertEqualsWithDelta(150, (float) $fine->frozen_amount, 0.001);
        $this->assertEqualsWithDelta(850, (float) $this->wallet()->balance, 0.001, 'money moved out of spendable');
        $this->assertEqualsWithDelta(150, (float) $this->wallet()->locked_balance, 0.001, 'and into locked, not gone');
    }

    public function test_an_accepted_appeal_returns_the_whole_hold(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 200, 'إساءة', (int) $this->admin->id);
        $this->fines->appeal($fine, (int) $this->user->id, 'لم أفعل شيئًا');

        $fine = $this->fines->decideAppeal($fine->fresh(), (int) $this->admin->id, true, 'محق');

        $this->assertSame(Fine::STATUS_OVERTURNED, $fine->status);
        $this->assertEqualsWithDelta(1000, (float) $this->wallet()->balance, 0.001, 'hold released back');
        $this->assertEqualsWithDelta(0, (float) $this->wallet()->locked_balance, 0.001);
    }

    public function test_a_rejected_appeal_captures_the_fine_to_the_treasury(): void
    {
        $treasury = app(PlatformTreasuryService::class);
        $before = $treasury->balanceByPurpose()['fine'] ?? 0;

        $fine = $this->fines->levy((int) $this->user->id, 120, 'احتيال', (int) $this->admin->id);
        $this->fines->appeal($fine, (int) $this->user->id, 'اعتراض');
        $fine = $this->fines->decideAppeal($fine->fresh(), (int) $this->admin->id, false, 'مرفوض');

        $this->assertSame(Fine::STATUS_COLLECTED, $fine->status);
        $this->assertEqualsWithDelta(880, (float) $this->wallet()->balance, 0.001);
        $this->assertEqualsWithDelta(0, (float) $this->wallet()->locked_balance, 0.001, 'the hold was captured, not left');

        $after = $treasury->balanceByPurpose()['fine'] ?? 0;
        $this->assertEqualsWithDelta(120, $after - $before, 0.001, 'the treasury fine bucket grew by the fine');
    }

    public function test_an_unappealed_fine_is_collected_once_the_window_closes(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 90, 'احتيال', (int) $this->admin->id, appealDays: 7);

        // Nothing collects while the window is open.
        $this->fines->processDue();
        $this->assertSame(Fine::STATUS_FROZEN, $fine->fresh()->status);

        // Close the window and sweep.
        $fine->update(['appeal_deadline_at' => now()->subDay()]);
        $r = $this->fines->processDue();

        $this->assertSame(1, $r['collected']);
        $this->assertSame(Fine::STATUS_COLLECTED, $fine->fresh()->status);
    }

    public function test_a_broke_user_is_still_fined_and_topped_up_before_capture(): void
    {
        $this->wallet()->update(['balance' => 50, 'locked_balance' => 0]);

        $fine = $this->fines->levy((int) $this->user->id, 200, 'احتيال', (int) $this->admin->id, appealDays: 7);

        // Only what was there could be frozen; a shortfall remains.
        $this->assertEqualsWithDelta(50, (float) $fine->frozen_amount, 0.001);
        $this->assertEqualsWithDelta(150, $fine->shortfall(), 0.001);

        // Window closes but it is under-frozen: no capture yet.
        $fine->update(['appeal_deadline_at' => now()->subDay()]);
        $this->fines->processDue();
        $this->assertNotSame(Fine::STATUS_COLLECTED, $fine->fresh()->status, 'never capture more than is locked');

        // Money arrives; the sweep tops up the freeze and then captures.
        $this->wallet()->update(['balance' => (float) $this->wallet()->balance + 200]);
        $this->fines->processDue();

        $fine = $fine->fresh();
        $this->assertSame(Fine::STATUS_COLLECTED, $fine->status);
        $this->assertEqualsWithDelta(200, (float) $fine->collected_amount, 0.001);
    }

    public function test_a_cancelled_fine_releases_the_hold(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 100, 'خطأ', (int) $this->admin->id);
        $fine = $this->fines->cancel($fine, (int) $this->admin->id, 'رجوع');

        $this->assertSame(Fine::STATUS_CANCELLED, $fine->status);
        $this->assertEqualsWithDelta(1000, (float) $this->wallet()->balance, 0.001);
        $this->assertEqualsWithDelta(0, (float) $this->wallet()->locked_balance, 0.001);
    }

    public function test_a_consent_born_fine_cannot_be_appealed(): void
    {
        $fine = $this->fines->levy(
            (int) $this->user->id, 80, 'تسوية', (int) $this->admin->id,
            appealable: false, source: Fine::SOURCE_SETTLEMENT
        );

        $this->assertFalse($fine->is_appealable);
        $this->assertNull($fine->appeal_deadline_at);

        $this->expectException(ValidationException::class);
        $this->fines->appeal($fine, (int) $this->user->id, 'محاولة');
    }

    public function test_you_cannot_appeal_after_the_window_closes(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 60, 'احتيال', (int) $this->admin->id, appealDays: 7);
        $fine->update(['appeal_deadline_at' => now()->subMinute()]);

        $this->expectException(ValidationException::class);
        $this->fines->appeal($fine->fresh(), (int) $this->user->id, 'متأخر');
    }

    // ─────────────────────────── the API ───────────────────────────

    public function test_a_user_sees_and_appeals_their_own_fine_over_the_api(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 110, 'احتيال', (int) $this->admin->id, appealDays: 7);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v2/fines')->assertOk()
            ->assertJsonPath('data.0.id', (int) $fine->id);

        $this->postJson("/api/v2/fines/{$fine->id}/appeal", ['statement' => 'هذا خطأ'])
            ->assertOk();

        $this->assertSame(Fine::STATUS_APPEALED, $fine->fresh()->status);
        $this->assertDatabaseHas('fine_appeals', [
            'fine_id' => $fine->id, 'user_id' => (int) $this->user->id, 'status' => FineAppeal::STATUS_PENDING,
        ]);
    }

    public function test_another_users_fine_is_404_not_403(): void
    {
        $fine = $this->fines->levy((int) $this->user->id, 70, 'احتيال', (int) $this->admin->id);

        $stranger = User::query()->where('id', '!=', $this->user->id)->orderBy('id')->firstOrFail();
        Sanctum::actingAs($stranger);

        $this->getJson("/api/v2/fines/{$fine->id}")->assertNotFound();
        $this->postJson("/api/v2/fines/{$fine->id}/appeal", ['statement' => 'x'])->assertNotFound();
    }
}
