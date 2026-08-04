<?php

namespace Tests\Feature;

use App\Models\CategoryChild;
use App\Services\CategoryChildOptionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The same child under two roots is two different businesses.
 *
 * «آثاث» is a workshop under ورش, a showroom under معارض, a plant under مصانع.
 * `category_child_option` was keyed by the child alone, so all of them shared
 * one answer sheet: a factory was asked about instalments, a showroom about
 * production capacity. `category_id` on the link splits them — 0 meaning "under
 * every root", a real id meaning that root alone.
 *
 * @see \App\Services\CategoryChildOptionScope
 */
class ChildOptionPerRootTest extends TestCase
{
    use DatabaseTransactions;

    private CategoryChildOptionScope $scope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scope = app(CategoryChildOptionScope::class);
    }

    /** A child that really does sit under more than one root, with its roots. */
    private function sharedChild(): array
    {
        $row = DB::table('category_parent_child')
            ->select('child_id')
            ->groupBy('child_id')
            ->havingRaw('COUNT(DISTINCT parent_id) > 1')
            ->first();

        if (! $row) {
            $this->markTestSkipped('No child sits under more than one root.');
        }

        $roots = DB::table('category_parent_child')
            ->where('child_id', $row->child_id)
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return [(int) $row->child_id, $roots];
    }

    /** The whole point: two roots, two sets, neither reaching the other. */
    public function test_two_roots_hold_different_sets_for_the_same_child(): void
    {
        [$childId, $roots] = $this->sharedChild();
        [$factory, $showroom] = [$roots[0], $roots[1]];

        $options = DB::table('options')->orderBy('id')->limit(6)->pluck('id')
            ->map(fn ($id) => (int) $id)->values();

        $this->assertCount(6, $options, 'the catalogue needs six options for this test');

        // 1-2-3-4 under one root, 4-5-6 under the other
        $this->scope->syncFor($childId, $factory, $options->slice(0, 4)->all());
        $this->scope->syncFor($childId, $showroom, $options->slice(3, 3)->all());

        $this->assertEqualsCanonicalizing(
            $options->slice(0, 4)->all(),
            $this->scope->idsFor($childId, $factory)->all()
        );

        $this->assertEqualsCanonicalizing(
            $options->slice(3, 3)->all(),
            $this->scope->idsFor($childId, $showroom)->all()
        );
    }

    /**
     * Withdrawing a SHARED option under one root must not withdraw it from the
     * others — the row is split, not deleted.
     */
    public function test_dropping_a_shared_option_under_one_root_leaves_the_others_holding_it(): void
    {
        [$childId, $roots] = $this->sharedChild();
        $mine = $roots[0];

        $option = (int) DB::table('options')->orderBy('id')->value('id');

        DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $option)->delete();
        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => CategoryChildOptionScope::ALL_ROOTS,
            'option_id' => $option,
        ]);

        $result = $this->scope->revokeFor($childId, $mine, [$option]);

        $this->assertSame(1, $result['split'], 'a shared row must be split, not simply deleted');
        $this->assertNotContains($option, $this->scope->idsFor($childId, $mine)->all());

        foreach ($roots->skip(1) as $other) {
            $this->assertContains(
                $option,
                $this->scope->idsFor($childId, $other)->all(),
                "root #{$other} lost an option it never asked to lose"
            );
        }
    }

    /** Nothing is written per root until a root disagrees. */
    public function test_a_set_that_matches_the_shared_rows_writes_nothing(): void
    {
        [$childId, $roots] = $this->sharedChild();

        $shared = $this->scope->sharedIds($childId);

        if ($shared->isEmpty()) {
            $this->markTestSkipped('This child carries no shared options.');
        }

        $before = DB::table('category_child_option')->where('child_id', $childId)->count();

        $result = $this->scope->syncFor($childId, $roots[0], $this->scope->idsFor($childId, $roots[0])->all());

        $this->assertSame(['added' => 0, 'removed' => 0, 'split' => 0], $result);
        $this->assertSame($before, DB::table('category_child_option')->where('child_id', $childId)->count());
    }

    /** A root cannot see, or delete, what belongs to another root. */
    public function test_a_root_specific_option_is_invisible_to_the_other_roots(): void
    {
        [$childId, $roots] = $this->sharedChild();
        [$mine, $other] = [$roots[0], $roots[1]];

        $option = (int) DB::table('options')->orderByDesc('id')->value('id');

        DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $option)->delete();

        $this->scope->grantFor($childId, $mine, [$option]);

        $this->assertContains($option, $this->scope->idsFor($childId, $mine)->all());
        $this->assertNotContains($option, $this->scope->idsFor($childId, $other)->all());

        // and the model relation agrees with the service
        $child = CategoryChild::query()->find($childId);

        $this->assertContains($option, $child->optionsForParent($mine)->pluck('options.id')->map(fn ($id) => (int) $id)->all());
        $this->assertNotContains($option, $child->optionsForParent($other)->pluck('options.id')->map(fn ($id) => (int) $id)->all());
    }

    /** Asking without a root still answers the union, for callers that span roots. */
    public function test_no_root_means_the_union_over_every_root(): void
    {
        [$childId, $roots] = $this->sharedChild();

        $option = (int) DB::table('options')->orderByDesc('id')->value('id');

        DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $option)->delete();
        $this->scope->grantFor($childId, $roots[1], [$option]);

        $this->assertContains($option, $this->scope->idsFor($childId, 0)->all());
    }

    /** The screen saves this root and reports the split, without touching the rest. */
    public function test_the_workbench_saves_one_root_only(): void
    {
        [$childId, $roots] = $this->sharedChild();
        [$mine, $other] = [$roots[0], $roots[1]];

        $shared = $this->scope->sharedIds($childId);

        if ($shared->isEmpty()) {
            $this->markTestSkipped('This child carries no shared options.');
        }

        // never withdraw one a merchant already ticked — the screen refuses, and
        // the assertion below would then be measuring the guard, not the split
        $ticked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.category_child_id', $childId)
            ->where('u.category_id', $mine)
            ->pluck('ou.option_id')
            ->map(fn ($id) => (int) $id);

        $droppable = $shared->diff($ticked);

        if ($droppable->isEmpty()) {
            $this->markTestSkipped('Every shared option here is pinned by a merchant.');
        }

        $drop = $droppable->first();
        $keep = $this->scope->idsFor($childId, $mine)->reject(fn ($id) => $id === $drop)->values();

        $this->actingAs($this->admin())
            ->post(route('admin.child-workbench.options', [], false), [
                'root_id' => $mine,
                'child_id' => $childId,
                'option_ids' => $keep->all(),
            ])
            ->assertRedirect();

        $this->assertNotContains($drop, $this->scope->idsFor($childId, $mine)->all());
        $this->assertContains($drop, $this->scope->idsFor($childId, $other)->all());
    }

    private function admin()
    {
        $admin = \App\Models\User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }
}
