<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Models\User;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One picker, many children.
 *
 * The bulk screen shows a SINGLE «الفروع والأنواع المسموحة» picker for a whole
 * batch and fills it from the FIRST selected child. Saving then wrote that one
 * list to every selected child, so ticking five children to turn a service ON
 * also silently answered a question nobody asked — which kinds each of them
 * sells.
 *
 * It really happened: on 2026-08-07 22:04 مستشفى، عيادة، مركز طبي، نادي صحي and
 * معمل تحاليل all came out of one save carrying the same five kinds. A
 * laboratory was left offering «إجراء طبي» and a health club offering «كشف»,
 * while the lab lost «سحب عينة بالمنزل» — the one kind it exists for. The
 * per-child assignment in service_kinds.php and everything set in the child
 * workbench went with it.
 *
 * The rule now: a vocabulary reaches the batch only when a human moved a
 * checkbox in THIS save. Otherwise every child keeps its own.
 */
class BulkSaveKeepsChildKindsTest extends TestCase
{
    use DatabaseTransactions;

    private int $rootId;

    private int $serviceId;

    /** @var array<int,int> two children under the same root */
    private array $childIds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)
            ->where('is_active', 1)
            ->value('id');

        if ($this->serviceId <= 0) {
            $this->markTestSkipped('The booking service is not active.');
        }

        $root = DB::table('category_parent_child')
            ->select('parent_id', DB::raw('COUNT(*) as n'))
            ->groupBy('parent_id')
            ->having('n', '>=', 2)
            ->orderByDesc('n')
            ->first();

        if (! $root) {
            $this->markTestSkipped('Needs a root with at least two children.');
        }

        $this->rootId = (int) $root->parent_id;
        $this->childIds = DB::table('category_parent_child')
            ->where('parent_id', $this->rootId)
            ->orderBy('child_id')
            ->limit(2)
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    /** Two booking types that really exist, so the picker values are honest. */
    private function twoTypeKeys(): array
    {
        $keys = DB::table('platform_service_item_types')
            ->where('platform_service_id', $this->serviceId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->limit(2)
            ->pluck('key')
            ->map(fn ($k) => (string) $k)
            ->all();

        if (count($keys) < 2) {
            $this->markTestSkipped('Needs two active booking item types.');
        }

        return $keys;
    }

    private function give(int $childId, array $kinds, array $groups = []): void
    {
        app(ChildServiceWriter::class)->enable(
            rootId: $this->rootId,
            childId: $childId,
            serviceId: $this->serviceId,
            configPatch: ['allowed_item_types' => $kinds, 'item_groups' => $groups]
        );
    }

    private function kindsOf(int $childId): array
    {
        return app(ChildServiceWriter::class)
            ->storedConfig($this->rootId, $childId, $this->serviceId)['allowed_item_types'] ?? [];
    }

    private function groupsOf(int $childId): array
    {
        return app(ChildServiceWriter::class)
            ->storedConfig($this->rootId, $childId, $this->serviceId)['item_groups'] ?? [];
    }

    private function save(array $extra = [])
    {
        return $this->actingAs($this->admin())->post(
            route('admin.categories.services-bulk.apply', [], false),
            array_merge([
                'root_id' => $this->rootId,
                'category_ids' => $this->childIds,
                'platform_service_ids' => [$this->serviceId],
                'mode' => 'append',
            ], $extra)
        );
    }

    /**
     * The damage, reproduced and refused: the picker carries the first child's
     * list, the admin never touched it, so the second child keeps its own.
     */
    public function test_an_untouched_picker_leaves_every_child_its_own_kinds(): void
    {
        [$a, $b] = $this->childIds;
        [$first, $second] = $this->twoTypeKeys();

        $this->give($a, [$first]);
        $this->give($b, [$second]);

        // Exactly what the browser posts after prefilling from child A.
        $this->save(['allowed_item_types' => [$this->serviceId => [$first]]])
            ->assertRedirect();

        $this->assertSame([$first], $this->kindsOf($a));
        $this->assertSame([$second], $this->kindsOf($b), 'the batch flattened a child onto its neighbour');
    }

    /** Move a checkbox and you mean it — then it applies to the whole batch. */
    public function test_a_touched_picker_applies_to_every_selected_child(): void
    {
        [$a, $b] = $this->childIds;
        [$first, $second] = $this->twoTypeKeys();

        $this->give($a, [$first]);
        $this->give($b, [$second]);

        $this->save([
            'allowed_item_types' => [$this->serviceId => [$first, $second]],
            'types_touched' => [$this->serviceId => 1],
        ])->assertRedirect();

        $this->assertSame([$first, $second], $this->kindsOf($a));
        $this->assertSame([$first, $second], $this->kindsOf($b));
    }

    /** Nothing stored means nothing to protect — the submitted list stands. */
    public function test_a_child_with_no_kinds_yet_takes_what_was_submitted(): void
    {
        [$a] = $this->childIds;
        [$first] = $this->twoTypeKeys();

        $this->give($a, []);

        $this->save(['allowed_item_types' => [$this->serviceId => [$first]]])
            ->assertRedirect();

        $this->assertSame([$first], $this->kindsOf($a));
    }

    /** The branch ticks are the same proposal and answer the same way. */
    public function test_an_untouched_picker_leaves_the_branch_selection_alone(): void
    {
        [$a] = $this->childIds;

        $groupIds = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $this->serviceId)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($groupIds) < 2) {
            $this->markTestSkipped('Needs two booking branches.');
        }

        [$first] = $this->twoTypeKeys();
        $this->give($a, [$first], [$groupIds[0]]);

        $this->save(['item_groups' => [$this->serviceId => [$groupIds[1]]]])
            ->assertRedirect();

        $this->assertSame([$groupIds[0]], $this->groupsOf($a));
    }

    /**
     * A botched splice had replaced both toolbar counters with a NUL byte, a
     * line number and a duplicated copy of the file's own @php prelude, so the
     * JS looked for #selectedChildrenCount, found nothing, and the admin ticking
     * children got no count back — on the screen that writes to all of them.
     */
    public function test_the_selection_counters_are_intact(): void
    {
        $source = file_get_contents(resource_path('views/admin-v2/categories/services-bulk.blade.php'));

        $this->assertStringNotContainsString("\0", $source, 'the blade carries a NUL byte again');
        $this->assertStringContainsString('id="selectedChildrenCount"', $source);
        $this->assertStringContainsString('id="selectedServicesCount"', $source);

        $this->assertSame(
            1,
            substr_count($source, '$rootsSafe = collect($roots ?? []);'),
            'the @php prelude is duplicated into the markup again'
        );
    }
}
