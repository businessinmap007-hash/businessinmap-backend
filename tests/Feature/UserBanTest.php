<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\User;
use App\Services\BanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The standalone admin ban.
 *
 * A ban must do three things that are easy to do only two of: mark the account,
 * record the identity on the hashed block list so a re-register is caught, and
 * stop the token the user already holds. The tests pin all three, plus that an
 * unban lifts every part and that a banned user is refused mid-session.
 */
class UserBanTest extends TestCase
{
    use DatabaseTransactions;

    private BanService $bans;
    private User $user;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bans = app(BanService::class);

        // A throwaway user with a real email/phone to hash, not a shared fixture.
        $this->user = new User();
        $this->user->name = 'Ban Target';
        $this->user->email = 'ban-' . uniqid() . '@example.test';
        $this->user->phone = '0100' . random_int(1000000, 9999999);
        $this->user->password = 'secret-password';
        $this->user->type = User::TYPE_CLIENT;
        $this->user->api_token = Str::random(80);
        $this->user->save();

        $this->adminId = (int) User::query()->where('type', 'admin')->value('id')
            ?: (int) User::query()->orderBy('id')->value('id');
    }

    public function test_ban_marks_the_account_and_records_the_identity(): void
    {
        $this->assertFalse(BlockedIdentity::isBlocked($this->user->email, $this->user->phone));

        $this->bans->ban($this->user, 'احتيال', $this->adminId);

        $this->assertTrue($this->user->fresh()->isBanned());
        $this->assertSame('احتيال', $this->user->fresh()->ban_reason);
        $this->assertTrue(
            BlockedIdentity::isBlocked($this->user->email, $this->user->phone),
            'the identity is on the hashed block list so a re-register is caught'
        );
    }

    public function test_ban_revokes_live_tokens(): void
    {
        $token = $this->user->createToken('live')->plainTextToken;
        $this->assertSame(1, $this->user->tokens()->count());

        $this->bans->ban($this->user, null, $this->adminId);

        $this->assertSame(0, $this->user->fresh()->tokens()->count(), 'the session the user already had is killed');
    }

    public function test_unban_lifts_the_mark_and_the_block(): void
    {
        $this->bans->ban($this->user, 'خطأ', $this->adminId);
        $this->bans->unban($this->user->fresh(), $this->adminId);

        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->isBanned());
        $this->assertNull($fresh->ban_reason);
        $this->assertFalse(
            BlockedIdentity::isBlocked($fresh->email, $fresh->phone),
            'the person can register/sign in again'
        );
    }

    public function test_a_banned_user_is_refused_on_an_authenticated_request(): void
    {
        // A token issued, THEN a ban applied out of band (not via login).
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v2/fines')->assertOk(); // baseline: fine before the ban

        $this->user->banned_at = now();
        $this->user->save();

        $this->getJson('/api/v2/fines')->assertStatus(403);
    }

    public function test_admin_cannot_ban_their_own_account_over_the_web(): void
    {
        $admin = User::query()->where('type', 'admin')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $admin->id), ['reason' => 'x'])
            ->assertRedirect();

        $this->assertFalse($admin->fresh()->isBanned(), 'a self-ban is refused');
    }
}
