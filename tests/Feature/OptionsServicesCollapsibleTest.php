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

    /**
     * «اخفى باقى مجموعات الخيارات الغير مختارة فى زر other وايضا جروبات
     *  المجموعات اخفيها كلها … ويظهر فقط ما تم الضغط عليه».
     *
     * Collapsed was not enough: forty shells still filled the page, and the axis
     * actually being written was never in view — which is how a door-and-window
     * list was saved onto a whole root of factories. Nothing renders now until a
     * chip is pressed, and pressing one closes the last.
     */
    public function test_the_bulk_picker_draws_no_group_until_one_is_asked_for(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit'))
            ->assertOk()
            ->getContent();

        $panels = preg_match_all('/class="[^"]*js-option-group-panel[^"]*"[^>]*>/', $html, $found);

        $this->assertGreaterThan(5, $panels, 'the screen still draws its groups');

        foreach ($found[0] as $tag) {
            $this->assertStringContainsString('display:none', $tag, 'every group starts hidden: ' . $tag);
        }

        // The chips are the only way in, so they must ship with the page.
        $this->assertStringContainsString('groupChipBar', $html);
        $this->assertStringContainsString('groupOtherBar', $html);
        $this->assertStringContainsString('renderGroupChips', $html);
    }

    /**
     * «لا يجب ان ترفع الشاشة لاعلى … ولا تتحرك إلا عندما اقوم بالاسكرول».
     *
     * Stepping to the next child used to scroll its card into view, which threw
     * the admin's place away on every press. The row is pinned where he is
     * looking instead, and the page moves only when he moves it.
     */
    public function test_stepping_between_children_never_scrolls_the_page(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('childPrev', $html);
        $this->assertStringContainsString('childNext', $html);

        $step = strstr($html, 'function stepChild');
        $step = $step === false ? '' : substr($step, 0, (int) strpos($step, 'const childPrev'));

        $this->assertNotSame('', $step, 'stepChild must exist to be checked');
        // The CALL, not the word — the code says in a comment why it is absent.
        $this->assertStringNotContainsString('.scrollIntoView(', $step, 'a step must not move the page');

        // And the row stays reachable while the child list is scrolled.
        $this->assertMatchesRegularExpression(
            '/id="childNav"[^>]*position:sticky/s',
            $html,
            'the nav row is pinned'
        );
    }

    public function test_services_bulk_renders(): void
    {
        $root = DB::table('categories')->where('parent_id', 0)->value('id');

        $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', $root ? ['root_id' => $root] : []))
            ->assertOk();
    }
}
