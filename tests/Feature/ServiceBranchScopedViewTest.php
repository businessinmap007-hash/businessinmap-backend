<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The service-scoped branch flow: a picker landing, then a page for ONE
 * service that shows only that service's branches — never another's.
 */
class ServiceBranchScopedViewTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->orderBy('id')->firstOrFail();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::CATALOG);
        Bouncer::refresh();

        return $admin;
    }

    public function test_landing_shows_the_service_picker_not_a_matrix(): void
    {
        $res = $this->actingAs($this->admin())->get('/admin/service-branches')->assertOk();

        // Every service appears as a pickable card linking to its own scoped page.
        foreach (PlatformService::query()->get() as $service) {
            $res->assertSee('service_id=' . $service->id, false);
        }
    }

    public function test_a_service_page_shows_only_its_own_branches(): void
    {
        $menu = PlatformService::query()->where('key', 'menu')->first();
        $booking = PlatformService::query()->where('key', 'booking')->first();

        if (! $menu || ! $booking) {
            $this->markTestSkipped('Needs the menu and booking services.');
        }

        $menuBranch = PlatformServiceItemGroup::query()->where('platform_service_id', $menu->id)->first();
        $bookingBranch = PlatformServiceItemGroup::query()->where('platform_service_id', $booking->id)->first();

        if (! $menuBranch || ! $bookingBranch) {
            $this->markTestSkipped('Needs a branch under both services.');
        }

        $res = $this->actingAs($this->admin())
            ->get('/admin/service-branches?service_id=' . $menu->id)
            ->assertOk();

        // The page hydrates from view data, so assert on that (the JSON in the
        // page is unicode-escaped, making a literal assertSee unreliable).
        $branchIds = collect($res->viewData('branches'))->pluck('id')->all();

        $this->assertContains($menuBranch->id, $branchIds, 'the service shows its own branch');
        $this->assertNotContains(
            $bookingBranch->id,
            $branchIds,
            'a branch from another service must never appear on this service page'
        );
    }

    public function test_a_new_branch_created_here_belongs_to_this_service(): void
    {
        $menu = PlatformService::query()->where('key', 'menu')->first();
        if (! $menu) {
            $this->markTestSkipped('Needs the menu service.');
        }

        $name = 'اختبار فرع ' . uniqid();

        $this->actingAs($this->admin())
            ->postJson('/admin/service-branches/branches', [
                'name_ar' => $name,
                'service_id' => $menu->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('platform_service_item_groups', [
            'name_ar' => $name,
            'platform_service_id' => $menu->id,
        ]);
    }
}
