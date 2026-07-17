<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * BIM-14.1 — that the abilities actually bite, over real HTTP.
 *
 * AdminAbilityCoverageTest proves every route declares an ability; this proves
 * the declaration does something. Rolls back.
 */
class AdminAbilityEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(array $abilities = []): User
    {
        $user = new User();
        $user->name = 'Ability Test Admin';
        $user->email = 'ability-' . uniqid() . '@example.test';
        $user->phone = '0155' . random_int(1000000, 9999999);
        $user->password = 'secret-password';
        $user->type = User::TYPE_ADMIN;
        $user->api_token = Str::random(80);
        $user->save();

        foreach ($abilities as $ability) {
            Bouncer::allow($user)->to($ability);
        }

        Bouncer::refresh();

        return $user->fresh();
    }

    /**
     * A real dispute row. Route-model binding runs before the ability check
     * (SubstituteBindings is in the `web` group, `can:` is route middleware), so
     * a made-up id answers 404 and proves nothing about authorization.
     */
    private function makeDispute(): Dispute
    {
        return Dispute::query()->create([
            'disputeable_type' => Booking::class,
            'disputeable_id' => 1,
            'type' => 'booking',
            'opened_by_user_id' => $this->makeAdmin()->id,
            'against_user_id' => $this->makeAdmin()->id,
            'status' => Dispute::STATUS_OPEN,
            'opened_at' => now(),
        ]);
    }

    public function test_being_an_admin_is_no_longer_enough_to_reach_everything(): void
    {
        // The exact account shape that used to have the run of the panel:
        // type = admin, nothing else.
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/wallet-transactions')->assertForbidden();
        $this->actingAs($admin)->get('/admin/service-fee-rules')->assertForbidden();
        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
    }

    public function test_an_ability_opens_its_own_domain_and_nothing_else(): void
    {
        $support = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES]);

        $this->actingAs($support)->get('/admin/disputes')->assertOk();

        // Not the treasury, not the fee rules, not the accounts.
        $this->actingAs($support)->get('/admin/wallet-transactions')->assertForbidden();
        $this->actingAs($support)->get('/admin/service-fee-rules')->assertForbidden();
        $this->actingAs($support)->get('/admin/users')->assertForbidden();
    }

    public function test_a_support_agent_can_triage_a_dispute_but_not_pay_anyone_out(): void
    {
        // The scenario this whole slice exists for.
        $support = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES]);
        $dispute = $this->makeDispute();

        $this->actingAs($support)->get('/admin/disputes')->assertOk();
        $this->actingAs($support)->get('/admin/disputes/' . $dispute->id)->assertOk();

        // A real dispute, so this 403 is the ability talking and not a missing row.
        $this->actingAs($support)->post('/admin/disputes/' . $dispute->id . '/resolve/refund-client')->assertForbidden();
        $this->actingAs($support)->post('/admin/disputes/' . $dispute->id . '/resolve/release-business')->assertForbidden();
        $this->actingAs($support)->post('/admin/disputes/' . $dispute->id . '/resolve/split')->assertForbidden();
    }

    public function test_adding_money_lets_the_same_agent_resolve(): void
    {
        $resolver = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::DISPUTES, AdminAbility::MONEY]);

        // Past the ability gate now, so it fails on the missing dispute instead
        // — which is the proof it got through.
        $this->actingAs($resolver)
            ->post('/admin/disputes/999999999/resolve/refund-client')
            ->assertNotFound();
    }

    public function test_the_money_ability_alone_does_not_open_the_dispute_queue(): void
    {
        // Both abilities are required, not either.
        $treasurer = $this->makeAdmin([AdminAbility::ACCESS, AdminAbility::MONEY]);
        $dispute = $this->makeDispute();

        $this->actingAs($treasurer)->get('/admin/wallet-transactions')->assertOk();
        $this->actingAs($treasurer)->get('/admin/disputes')->assertForbidden();
        $this->actingAs($treasurer)->post('/admin/disputes/' . $dispute->id . '/resolve/refund-client')->assertForbidden();
    }

    public function test_the_existing_super_admins_kept_every_door_open(): void
    {
        // The migration's whole job. If this fails, the real panel is bricked.
        foreach (User::query()->where('type', User::TYPE_ADMIN)->get() as $admin) {
            if ((int) $admin->id === (int) config('bim.platform_wallet_user_id')) {
                continue; // the treasury holds money, not powers
            }

            $this->assertTrue(
                $admin->can(AdminAbility::WILDCARD),
                "admin #{$admin->id} ({$admin->email}) lost access when abilities were introduced"
            );

            $this->actingAs($admin)->get('/admin/wallet-transactions')->assertOk();
        }
    }

    public function test_the_treasury_holds_money_but_no_panel_powers(): void
    {
        $treasuryId = (int) config('bim.platform_wallet_user_id');

        if ($treasuryId <= 0) {
            $this->markTestSkipped('Needs BIM_PLATFORM_WALLET_USER_ID.');
        }

        $treasury = User::query()->find($treasuryId);

        $this->assertFalse($treasury->can(AdminAbility::WILDCARD));
        $this->assertFalse($treasury->can(AdminAbility::MONEY), 'it is type=admin only so it is not a trading business');
    }

    public function test_a_non_admin_is_still_stopped_by_the_panel_middleware(): void
    {
        // The ability layer is added to the old check, not swapped for it.
        $client = new User();
        $client->name = 'Not An Admin';
        $client->email = 'client-' . uniqid() . '@example.test';
        $client->phone = '0166' . random_int(1000000, 9999999);
        $client->password = 'secret-password';
        $client->type = User::TYPE_CLIENT;
        $client->api_token = Str::random(80);
        $client->save();

        Bouncer::allow($client)->to(AdminAbility::MONEY); // even so
        Bouncer::refresh();

        $this->actingAs($client)->get('/admin/wallet-transactions')->assertRedirect();
    }
}
