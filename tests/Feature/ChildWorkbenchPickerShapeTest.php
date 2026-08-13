<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «هل يمكن عمل صفحة ادير منها خيارات وخدمات كل فرع بنفس الشكل والاسلوب لصفحة
 * category-child-options/bulk/edit».
 *
 * That page already exists — the child workbench is the single per-child door,
 * and it has managed options and services side by side since the service-config
 * consolidation. What it lacked was the bulk picker's WAY of working: chips
 * instead of a wall, one panel open at a time, and a walk from one child to the
 * next without hunting in a dropdown.
 *
 * A new screen would have undone the consolidation that reduced these to one.
 *
 * The load-bearing rule here, and the reason these tests exist: **panels are
 * hidden, never disabled.** Both forms save by REPLACING the whole set —
 * `option_ids[]` and `services[…][item_types][]` — so a disabled field is a
 * deleted answer, and a picker that hides two thirds of the page while
 * disabling it would wipe two thirds of the child on the next save.
 */
class ChildWorkbenchPickerShapeTest extends TestCase
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

    /** A child that actually carries options, so the panels are not empty. */
    private function loadedChild(): array
    {
        $row = DB::table('category_child_option as cco')
            ->join('category_parent_child as pc', 'pc.child_id', '=', 'cco.child_id')
            ->select('cco.child_id', 'pc.parent_id')
            ->groupBy('cco.child_id', 'pc.parent_id')
            ->havingRaw('COUNT(*) > 3')
            ->first();

        if (! $row) {
            $this->markTestSkipped('No child carries enough options.');
        }

        return [(int) $row->parent_id, (int) $row->child_id];
    }

    private function screen(?int $rootId = null, ?int $childId = null): string
    {
        [$root, $child] = $rootId && $childId ? [$rootId, $childId] : $this->loadedChild();

        return $this->actingAs($this->admin())
            ->get(route('admin.child-workbench.index', ['root_id' => $root, 'child_id' => $child]))
            ->assertOk()
            ->getContent();
    }

    /*
    |--------------------------------------------------------------------------
    | The shape
    |--------------------------------------------------------------------------
    */

    /** Both halves get a chip bar and an «Other» drawer. */
    public function test_options_and_services_each_get_chips(): void
    {
        $html = $this->screen();

        foreach (['options', 'services'] as $scope) {
            $this->assertStringContainsString('data-cw-bar="' . $scope . '"', $html, "«{$scope}» has no chip bar");
            $this->assertStringContainsString('data-cw-other="' . $scope . '"', $html, "«{$scope}» has no Other drawer");
        }
    }

    /** Every panel carries the key its chip addresses, and both scopes appear. */
    public function test_every_panel_is_addressable_by_a_chip(): void
    {
        $html = $this->screen();

        $this->assertMatchesRegularExpression('/js-cw-panel[^>]*data-cw-scope="options"[^>]*data-cw-key="/', $html);
        $this->assertMatchesRegularExpression('/js-cw-panel[^>]*data-cw-scope="services"[^>]*data-cw-key="/', $html);
    }

    /** What the child already answers is a primary chip, not buried in «Other». */
    public function test_a_held_panel_is_marked_held(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString('data-cw-held="1"', $html, 'nothing is marked as already answered');
    }

    /*
    |--------------------------------------------------------------------------
    | The rule the whole thing rests on
    |--------------------------------------------------------------------------
    */

    /**
     * Hidden, never disabled. Both forms replace the whole set on save, so a
     * disabled checkbox is an answer deleted by looking away from it.
     */
    public function test_closing_a_panel_hides_it_and_never_disables_it(): void
    {
        $html = $this->screen();

        $start = strpos($html, 'function reveal(scope, key)');
        $this->assertNotFalse($start, 'the reveal is gone');

        $body = substr($html, $start, 900);

        $this->assertStringContainsString("panel.style.display", $body);
        $this->assertStringNotContainsString('disabled = true', $body);
        $this->assertStringNotContainsString('.disabled', $body, 'a closed panel must still submit its answers');
    }

    /*
    |--------------------------------------------------------------------------
    | The walk
    |--------------------------------------------------------------------------
    */

    /** Prev and next either side of the name, on a pinned row. */
    public function test_the_walk_sits_beside_the_name_and_is_pinned(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString('id="childNav"', $html);

        $nav = substr($html, strpos($html, 'id="childNav"'), 1200);

        $this->assertStringContainsString('position:sticky', $nav);
        $this->assertLessThan(strpos($nav, 'id="childNavName"'), strpos($nav, 'id="childPrev"'));
        $this->assertLessThan(strpos($nav, 'id="childNext"'), strpos($nav, 'id="childNavName"'));
    }

    /** The steps point at real neighbours, and the position reads honestly. */
    public function test_the_steps_point_at_the_neighbouring_children(): void
    {
        [$root, $child] = $this->loadedChild();

        $siblings = DB::table('category_parent_child as pc')
            ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
            ->where('pc.parent_id', $root)
            ->orderByRaw('COALESCE(c.reorder, 999999) ASC')
            ->orderBy('c.name_ar')
            ->orderBy('c.id')
            ->pluck('c.id');

        if ($siblings->count() < 2) {
            $this->markTestSkipped('This root has a single child — no walk to take.');
        }

        $html = $this->screen($root, $child);

        preg_match('/id="childPrev"\s+href="([^"]+)"/', $html, $prev);
        preg_match('/id="childNext"\s+href="([^"]+)"/', $html, $next);

        $this->assertNotEmpty($prev, 'no previous link');
        $this->assertNotEmpty($next, 'no next link');

        foreach ([$prev[1], $next[1]] as $href) {
            preg_match('/child_id=(\d+)/', html_entity_decode($href), $m);

            $this->assertNotEmpty($m, "«{$href}» names no child");
            $this->assertTrue(
                $siblings->contains((int) $m[1]),
                'a step leaves the root it is walking'
            );
            $this->assertNotSame($child, (int) $m[1], 'a step goes nowhere');
        }
    }

    /**
     * A step is a page load here, so «لا يجب ان ترفع الشاشة لاعلى» is kept by
     * carrying the scroll position across rather than by not scrolling.
     */
    public function test_a_step_carries_the_scroll_position_across(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString('sessionStorage.setItem', $html);
        $this->assertStringContainsString('window.scrollTo(0,', $html);
    }

    /** The workbench remains one door: both forms still post where they did. */
    public function test_it_is_still_one_page_saving_both_halves(): void
    {
        $html = $this->screen();

        $this->assertStringContainsString(route('admin.child-workbench.options', [], false), $html);
        $this->assertStringContainsString(route('admin.child-workbench.services', [], false), $html);
    }
}
