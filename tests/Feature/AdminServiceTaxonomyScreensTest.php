<?php

namespace Tests\Feature;

use App\Models\PlatformServiceItemGroup;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the existing admin screens that edit the service taxonomy and
 * redistribute it onto business specializations actually render for a CATALOG
 * admin and are gated off from one without the ability:
 *   - service branches board (edit groups + tick types into them)
 *   - item-group (branch) management
 *   - bulk service→category assignment (the redistribution onto specializations)
 */
class AdminServiceTaxonomyScreensTest extends TestCase
{
    use DatabaseTransactions;

    private function catalogAdmin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::CATALOG] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    public function test_the_service_branches_board_renders(): void
    {
        // There is a real taxonomy to organise.
        $this->assertGreaterThan(0, PlatformServiceItemGroup::query()->count());

        $this->actingAs($this->catalogAdmin())
            ->get('/admin/service-branches')
            ->assertOk()
            // The board links to the sibling item-group screen — proof it rendered.
            ->assertSee('/admin/platform-service-item-groups', false);
    }

    public function test_the_item_group_management_screen_renders(): void
    {
        $this->actingAs($this->catalogAdmin())
            ->get('/admin/platform-service-item-groups')
            ->assertOk();
    }

    public function test_the_bulk_service_to_category_screen_renders(): void
    {
        $this->actingAs($this->catalogAdmin())
            ->get('/admin/categories/services-bulk')
            ->assertOk();
    }

    public function test_an_admin_without_catalog_is_forbidden(): void
    {
        $plain = new User();
        $plain->name = 'Plain Admin';
        $plain->email = 'plaincat-' . uniqid() . '@example.test';
        $plain->phone = '0159' . random_int(1000000, 9999999);
        $plain->password = 'secret-password';
        $plain->type = User::TYPE_ADMIN;
        $plain->api_token = Str::random(80);
        $plain->save();

        \Bouncer::allow($plain)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        $this->actingAs($plain)->get('/admin/service-branches')->assertForbidden();
    }
}
