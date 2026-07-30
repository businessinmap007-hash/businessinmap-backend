<?php

namespace Tests\Feature;

use App\Models\CategoryChild;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The previous (live) options & services admin screens now render their groups
 * COLLAPSED by default (click a group to reveal its contents), so the screen is
 * not crowded. These tests mainly prove the blades still compile and render, and
 * that the option groups no longer open by default.
 */
class OptionsServicesCollapsibleTest extends TestCase
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

    public function test_child_options_edit_renders_with_collapsed_groups(): void
    {
        $child = CategoryChild::query()->orderBy('id')->first();
        if (! $child) {
            $this->markTestSkipped('Needs a category child.');
        }

        $html = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.edit', ['categoryChild' => $child->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('js-group-card', $html, 'group cards render');
        // Collapsed by default: a <details ...> that is immediately followed by the
        // open attribute must NOT exist for our group cards.
        $this->assertDoesNotMatchRegularExpression(
            '/js-group-card"\s+open/s',
            $html,
            'group cards must not be open by default'
        );
        // The expand/collapse-all controls are still present.
        $this->assertStringContainsString('expandAllGroupsBtn', $html);
    }

    public function test_child_options_bulk_edit_renders_as_accordion(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit'))
            ->assertOk()
            ->getContent();

        // Converted from tabs to <details> accordion panels.
        $this->assertStringContainsString('js-option-group-panel', $html);
        $this->assertStringNotContainsString('js-option-group-tab', $html, 'the old tab buttons are gone');
    }

    public function test_services_bulk_renders(): void
    {
        $root = DB::table('categories')->where('parent_id', 0)->value('id');

        $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', $root ? ['root_id' => $root] : []))
            ->assertOk();
    }
}
