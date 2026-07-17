<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\AdminAbilityService;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * BIM-14.1 — the roles screen.
 *
 * This screen is the root of the permission system: whoever can hand out
 * `admin.money` effectively has it. Every test here is a way somebody would try
 * to turn "I can edit permissions" into "I can do anything". Rolls back.
 */
class AdminRoleScreenTest extends TestCase
{
    use DatabaseTransactions;

    private AdminAbilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AdminAbilityService::class);
    }

    private function makeAdmin(array $abilities = [], string $type = User::TYPE_ADMIN): User
    {
        $user = new User();
        $user->name = 'Roles Test';
        $user->email = 'roles-' . uniqid() . '@example.test';
        $user->phone = '0177' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = $type;
        $user->api_token = Str::random(80);
        $user->save();

        foreach ($abilities as $ability) {
            Bouncer::allow($user)->to($ability);
        }

        Bouncer::refresh();

        return $user->fresh();
    }

    private function superAdmin(): User
    {
        $user = $this->makeAdmin();
        Bouncer::allow($user)->everything();
        Bouncer::refresh();

        return $user->fresh();
    }

    // ------------------------------------------------------- the escalation

    public function test_you_cannot_grant_an_ability_you_do_not_hold(): void
    {
        // The rule the whole screen rests on. Without it, ROLES quietly equals
        // every other ability at once.
        $lead = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES, AdminAbility::ROLES]);
        $target = $this->makeAdmin([AdminAbility::ACCESS]);

        $this->expectException(\RuntimeException::class);
        $this->service->sync($lead, $target, [AdminAbility::ACCESS, AdminAbility::MONEY]);
    }

    public function test_the_escalation_is_refused_over_http_too(): void
    {
        $lead = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES, AdminAbility::ROLES]);
        $target = $this->makeAdmin([AdminAbility::ACCESS]);

        $this->actingAs($lead)
            ->put('/admin/admin-roles/' . $target->id, ['abilities' => [AdminAbility::MONEY]])
            ->assertRedirect()
            ->assertSessionHasErrors('abilities');

        $this->assertNotContains(AdminAbility::MONEY, $this->service->abilitiesOf($target->fresh()));
    }

    public function test_you_can_grant_exactly_what_you_hold(): void
    {
        $lead = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES, AdminAbility::ROLES]);
        $target = $this->makeAdmin();

        $this->actingAs($lead)
            ->put('/admin/admin-roles/' . $target->id, [
                'abilities' => [AdminAbility::ACCESS, AdminAbility::DISPUTES],
            ])
            ->assertRedirect(route('admin.admin-roles.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            [AdminAbility::ACCESS, AdminAbility::DISPUTES],
            $this->service->abilitiesOf($target->fresh())
        );
    }

    public function test_you_cannot_edit_your_own_abilities(): void
    {
        // Closes self-escalation and self-lockout with one rule.
        $lead = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::ROLES]);

        $this->assertNotNull($this->service->blockReason($lead, $lead));

        $this->actingAs($lead)
            ->get('/admin/admin-roles/' . $lead->id . '/edit')
            ->assertRedirect(route('admin.admin-roles.index'));
    }

    public function test_a_lesser_admin_cannot_touch_a_super_admin(): void
    {
        $lead = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::ROLES]);
        $super = $this->superAdmin();

        $this->assertNotNull($this->service->blockReason($lead, $super));

        $this->expectException(\RuntimeException::class);
        $this->service->sync($lead, $super, []);
    }

    public function test_even_a_super_admin_cannot_strip_another_super_admin_here(): void
    {
        // The panel must not be brickable from a web form.
        $super = $this->superAdmin();
        $otherSuper = $this->superAdmin();

        // And the screen says so up front rather than offering an Edit button
        // that leads to an error.
        $this->assertNotNull($this->service->blockReason($super, $otherSuper));

        $this->expectException(\RuntimeException::class);
        $this->service->sync($super, $otherSuper, [AdminAbility::ACCESS]);
    }

    public function test_abilities_above_the_actors_level_survive_an_edit(): void
    {
        // A disabled checkbox posts nothing, which reads as "remove it". Someone
        // who cannot grant MONEY must not be able to revoke it by accident.
        $lead = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES, AdminAbility::ROLES]);
        $target = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::MONEY]);

        $this->actingAs($lead)
            ->put('/admin/admin-roles/' . $target->id, ['abilities' => [AdminAbility::ACCESS, AdminAbility::DISPUTES]])
            ->assertSessionHas('success');

        $held = $this->service->abilitiesOf($target->fresh());

        $this->assertContains(AdminAbility::MONEY, $held, 'the lead could not grant MONEY, so it must not be able to revoke it either');
        $this->assertContains(AdminAbility::DISPUTES, $held, 'what it could grant, it did');
    }

    public function test_revoking_within_your_own_scope_works(): void
    {
        $super = $this->superAdmin();
        $target = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::MONEY, AdminAbility::DISPUTES]);

        $this->service->sync($super, $target, [AdminAbility::ACCESS]);

        $this->assertSame([AdminAbility::ACCESS], $this->service->abilitiesOf($target->fresh()));
    }

    // ----------------------------------------------------------- the screen

    public function test_the_screen_needs_its_own_ability_not_settings(): void
    {
        // If SETTINGS opened this, SETTINGS would silently equal everything.
        $settingsAdmin = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::SETTINGS]);

        $this->actingAs($settingsAdmin)->get('/admin/push-settings')->assertOk();
        $this->actingAs($settingsAdmin)->get('/admin/admin-roles')->assertForbidden();
    }

    public function test_the_roles_screen_renders(): void
    {
        $super = $this->superAdmin();
        $target = $this->makeAdmin([AdminAbility::ACCESS]);

        $this->actingAs($super)
            ->get('/admin/admin-roles')
            ->assertOk()
            ->assertSee($target->email);

        $this->actingAs($super)
            ->get('/admin/admin-roles/' . $target->id . '/edit')
            ->assertOk()
            ->assertSee(AdminAbility::label(AdminAbility::MONEY));
    }

    public function test_the_treasury_is_not_listed_as_a_person_with_powers(): void
    {
        $treasuryId = (int) config('bim.platform_wallet_user_id');

        if ($treasuryId <= 0) {
            $this->markTestSkipped('Needs BIM_PLATFORM_WALLET_USER_ID.');
        }

        $this->assertFalse(
            $this->service->manageableAdmins()->contains('id', $treasuryId),
            'the treasury holds money, not powers — listing it only invites someone to grant it some'
        );
    }

    public function test_a_non_admin_cannot_be_given_panel_abilities_here(): void
    {
        $super = $this->superAdmin();
        $client = $this->makeAdmin([], User::TYPE_CLIENT);

        $this->expectException(\RuntimeException::class);
        $this->service->sync($super, $client, [AdminAbility::MONEY]);
    }

    // ------------------------------------------------------------ the menu

    public function test_the_sidebar_hides_what_the_admin_cannot_open(): void
    {
        $support = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES]);

        $page = $this->actingAs($support)->get('/admin')->assertOk();

        // Its own screen is there; the money and fee screens are not links at all.
        $page->assertSee(route('admin.disputes.index'));
        $page->assertDontSee(route('admin.wallet-transactions.index'));
        $page->assertDontSee(route('admin.payment-settings.edit'));
    }

    public function test_a_super_admin_still_sees_the_whole_sidebar(): void
    {
        $page = $this->actingAs($this->superAdmin())->get('/admin')->assertOk();

        $page->assertSee(route('admin.wallet-transactions.index'));
        $page->assertSee(route('admin.admin-roles.index'));
    }

    public function test_the_dashboard_does_not_hand_out_the_money_it_guards(): void
    {
        // Gating the wallet screens alone would have moved this leak, not closed
        // it: the dashboard summed platform fees and listed real transactions
        // for anyone who could open the panel.
        $support = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES]);

        $this->actingAs($support)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Platform Fees')
            ->assertDontSee('ملخص المحفظة')
            ->assertDontSee('آخر معاملات المحفظة');
    }

    public function test_the_dashboard_still_shows_money_to_those_allowed_it(): void
    {
        $this->actingAs($this->makeAdmin([AdminAbility::ACCESS, AdminAbility::MONEY]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('ملخص المحفظة');
    }
}
