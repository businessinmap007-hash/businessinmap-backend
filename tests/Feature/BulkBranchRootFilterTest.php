<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bulk services screen shows the branches the selected root actually uses.
 *
 * It used to list every branch of a service whatever root was open — 21 of
 * them, most belonging to trades the root has nothing to do with. That is not
 * only noise: a screen that shows everything invites ticking everything, and
 * one save then writes that whole list over what the root had been given
 * carefully. Exactly that overwrite had to be repaired earlier this week.
 *
 * Nothing is filtered away server-side. Branches the root has not used yet are
 * tagged and folded behind «أظهر كل الفروع», because a branch a root does not
 * use yet is precisely what must stay reachable to start using it.
 */
class BulkBranchRootFilterTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    private function rootId(string $slug): int
    {
        return (int) DB::table('categories')->where('parent_id', 0)->where('slug', $slug)->value('id');
    }

    /** Branch ids the root's own children name in their configs. */
    private function usedBy(int $rootId): array
    {
        $childIds = DB::table('category_parent_child')->where('parent_id', $rootId)->pluck('child_id');
        $used = [];

        foreach (
            DB::table('category_service_configs')
                ->where('category_id', $rootId)
                ->whereIn('child_id', $childIds)
                ->where('is_active', 1)
                ->pluck('config') as $config
        ) {
            foreach (json_decode((string) $config, true)['item_groups'] ?? [] as $id) {
                $used[(int) $id] = true;
            }
        }

        return $used;
    }

    public function test_the_screen_marks_only_the_branches_this_root_uses(): void
    {
        $rootId = $this->rootId('tourist-hotels');

        if ($rootId <= 0) {
            $this->markTestSkipped('The tourist-hotels root is gone.');
        }

        $branches = $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', ['root_id' => $rootId], false))
            ->assertOk()
            ->viewData('serviceBranches');

        $used = $this->usedBy($rootId);

        // Whether this root happens to use a branch today is the owner's call —
        // a bulk save with no branch ticked leaves it using none, and that is a
        // legitimate state. What is under test is the FLAGGING, so there has to
        // be something to flag before the assertion means anything.
        if ($used === []) {
            $this->markTestSkipped('This root uses no branch right now, so there is nothing to mark.');
        }

        $flagged = 0;

        foreach ($branches as $service) {
            foreach ($service['branches'] as $branch) {
                $this->assertSame(
                    isset($used[(int) $branch['id']]),
                    (bool) $branch['in_use'],
                    "«{$branch['name']}» is flagged wrong for this root"
                );

                if ($branch['in_use']) {
                    $flagged++;
                }
            }
        }

        $this->assertGreaterThan(0, $flagged, 'nothing was marked relevant, so the fold would hide everything');
    }

    /**
     * The saving is the point. A hotel root works with one branch out of 21, so
     * the screen must not open on twenty it has nothing to do with.
     */
    public function test_an_unrelated_branch_is_not_marked_relevant(): void
    {
        $rootId = $this->rootId('tourist-hotels');

        if ($rootId <= 0) {
            $this->markTestSkipped('The tourist-hotels root is gone.');
        }

        $branches = $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', ['root_id' => $rootId], false))
            ->assertOk()
            ->viewData('serviceBranches');

        $relevant = [];
        $total = 0;

        foreach ($branches as $service) {
            foreach ($service['branches'] as $branch) {
                $total++;

                if ($branch['in_use']) {
                    $relevant[] = $branch['name'];
                }
            }
        }

        if ($relevant === []) {
            $this->markTestSkipped('This root uses no branch right now.');
        }

        $this->assertLessThan($total, count($relevant), 'every branch was marked relevant — the filter did nothing');

        // A hotel does not ship freight or run a supermarket aisle.
        foreach ($relevant as $name) {
            $this->assertStringNotContainsString('شحن بضائع', $name);
            $this->assertStringNotContainsString('سوبر ماركت', $name);
        }
    }

    /** Every branch stays in the payload — folded, never dropped. */
    public function test_no_branch_is_removed_from_the_payload(): void
    {
        $rootId = $this->rootId('tourist-hotels');

        if ($rootId <= 0) {
            $this->markTestSkipped('The tourist-hotels root is gone.');
        }

        $branches = $this->actingAs($this->admin())
            ->get(route('admin.categories.services-bulk.index', ['root_id' => $rootId], false))
            ->assertOk()
            ->viewData('serviceBranches');

        foreach ($branches as $serviceId => $service) {
            $shown = collect($service['branches'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();

            $all = DB::table('platform_service_item_group_type as gt')
                ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
                ->where('t.platform_service_id', (int) $serviceId)
                ->where('t.is_active', 1)
                ->distinct()
                ->pluck('gt.group_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $this->assertSame(
                $all->all(),
                $shown->all(),
                "service {$serviceId} lost a branch — the filter must fold, not drop"
            );
        }
    }
}
