<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionWithdrawals;
use App\Services\CategoryChildOptionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «انسحب البذرة، اتبع تنظيمي اليدوي» — owner, 2026-08-10.
 *
 * The option seeders are add-only, which was the right call: an add-only seeder
 * cannot destroy curation. What it could not do is tell «never granted» from
 * «granted and then removed», so every run handed straight back what the owner
 * had just unticked — the whole furniture list onto twenty-two carpenters, a
 * café given factory-gate freight terms, a supermarket given sandwiches.
 *
 * The record of removals is the missing half. These tests pin the three promises
 * it makes: a removal is remembered, a seeder honours it, and re-ticking forgets
 * it — because a mechanism that cannot be reversed is worse than the problem.
 */
class ChildOptionWithdrawalTest extends TestCase
{
    use DatabaseTransactions;

    private ChildOptionWithdrawals $withdrawals;

    private CategoryChildOptionScope $scope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withdrawals = app(ChildOptionWithdrawals::class);
        $this->scope = app(CategoryChildOptionScope::class);
    }

    /** A child standing under two or more roots, so the split paths are real. */
    private function childWithRoots(int $atLeast = 2): int
    {
        $id = DB::table('category_parent_child')
            ->select('child_id')
            ->groupBy('child_id')
            ->havingRaw('COUNT(*) >= ?', [$atLeast])
            ->value('child_id');

        $this->assertNotNull($id, 'no child stands under two roots');

        return (int) $id;
    }

    private function rootsOf(int $childId): array
    {
        return DB::table('category_parent_child')->where('child_id', $childId)
            ->pluck('parent_id')->map(fn ($id) => (int) $id)->all();
    }

    /** An option nobody has granted this child, so the test owns its state. */
    private function freeOption(int $childId): int
    {
        $held = DB::table('category_child_option')->where('child_id', $childId)->pluck('option_id');

        $id = DB::table('options')->whereNotIn('id', $held->isEmpty() ? [0] : $held->all())->value('id');

        $this->assertNotNull($id, 'every option is already on this child');

        return (int) $id;
    }

    /** Unticking through the admin's door leaves a record behind. */
    public function test_a_hand_removal_is_remembered(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        $this->scope->grantFor($childId, $rootId, [$optionId]);
        $this->scope->revokeFor($childId, $rootId, [$optionId]);

        $this->assertTrue(
            DB::table(ChildOptionWithdrawals::TABLE)
                ->where('child_id', $childId)->where('category_id', $rootId)
                ->where('option_id', $optionId)->exists(),
            'the removal left no record, so the next seeder run will undo it'
        );

        $this->assertContains($optionId, $this->withdrawals->idsFor($childId, $rootId)->all());
    }

    /** And a seeder asking what it may grant is told no. */
    public function test_a_withdrawn_option_is_filtered_out_of_a_grant(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        $this->assertSame([$optionId], $this->withdrawals->filter($childId, $rootId, [$optionId]));

        $this->withdrawals->record($childId, $rootId, [$optionId]);

        $this->assertSame([], $this->withdrawals->filter($childId, $rootId, [$optionId]));

        // A shared grant reaches every root, so one root saying no is enough.
        $this->assertSame([], $this->withdrawals->filter($childId, 0, [$optionId]));
    }

    /**
     * The reversal, and the reason a wrong row costs a click rather than a
     * migration. Ticking it back on must clear the record — otherwise the next
     * seeder run reads a stale «do not grant» and takes away what he just asked
     * for, which is the original bug with the sign flipped.
     */
    public function test_ticking_it_back_on_forgets_the_removal(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        $this->withdrawals->record($childId, $rootId, [$optionId]);
        $this->scope->grantFor($childId, $rootId, [$optionId]);

        $this->assertSame(
            [],
            $this->withdrawals->idsFor($childId, $rootId)->intersect([$optionId])->all(),
            'the option was granted again and the withdrawal survived'
        );
    }

    /**
     * A blanket withdrawal is not simply deleted when one root grants the
     * option — that would un-withdraw it everywhere off the back of a decision
     * about a single root. It is split, exactly as a shared option ROW is split
     * on the granting side.
     */
    public function test_granting_under_one_root_does_not_un_withdraw_the_others(): void
    {
        $childId = $this->childWithRoots();
        $roots = $this->rootsOf($childId);
        [$mine, $other] = [$roots[0], $roots[1]];
        $optionId = $this->freeOption($childId);

        $this->withdrawals->record($childId, ChildOptionWithdrawals::ALL_ROOTS, [$optionId]);

        $this->scope->grantFor($childId, $mine, [$optionId]);

        $this->assertSame([$optionId], $this->withdrawals->filter($childId, $mine, [$optionId]));
        $this->assertSame([], $this->withdrawals->filter($childId, $other, [$optionId]));
    }

    /**
     * The five broad seeders honour the record on their own. This is what the
     * per-seeder idempotency tests measure, and it is why consulting up front
     * is worth doing even though the reconciliation pass exists.
     *
     * @dataProvider broadSeeders
     */
    public function test_a_broad_seeder_does_not_hand_back_what_was_withdrawn(string $seeder): void
    {
        $before = DB::table('category_child_option')->count();

        (new $seeder)->run();

        $this->assertSame(
            $before,
            DB::table('category_child_option')->count(),
            class_basename($seeder) . ' granted something the owner had taken away'
        );
    }

    /** @return array<string,array{0:class-string}> */
    public static function broadSeeders(): array
    {
        return [
            'keyword rules' => [\Database\Seeders\LinkCategoryChildrenToOptionsSeeder::class],
            'scopes' => [\Database\Seeders\ChildOptionScopeSeeder::class],
            'groups' => [\Database\Seeders\ChildOptionGroupsSeeder::class],
            'menu bands' => [\Database\Seeders\MenuLineOptionsSeeder::class],
            'vehicles' => [\Database\Seeders\VehicleOptionGroupsSeeder::class],
        ];
    }

    /**
     * The backstop for the thirty-six seeders that were NOT rewritten: whatever
     * they grant, the last seeder in the chain takes back.
     */
    public function test_the_reconciliation_pass_removes_a_link_a_seeder_restored(): void
    {
        $childId = $this->childWithRoots();
        $optionId = $this->freeOption($childId);

        // A seeder that never asked, granting a shared row the owner refused.
        $this->withdrawals->record($childId, ChildOptionWithdrawals::ALL_ROOTS, [$optionId]);

        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => ChildOptionWithdrawals::ALL_ROOTS,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        (new \Database\Seeders\ChildOptionWithdrawalsSeeder)->run();

        $this->assertFalse(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            'the backstop let a withdrawn option through'
        );
    }

    /**
     * A withdrawal under ONE root may not strip the option from the child's
     * other roots. The shared row is handed to them explicitly first — the same
     * rule `CategoryChildOptionScope::splitShared()` follows, and the bug
     * per-root option rows exist to end.
     */
    public function test_a_one_root_withdrawal_leaves_the_other_roots_holding_it(): void
    {
        $childId = $this->childWithRoots();
        $roots = $this->rootsOf($childId);
        [$refusing, $other] = [$roots[0], $roots[1]];
        $optionId = $this->freeOption($childId);

        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => ChildOptionWithdrawals::ALL_ROOTS,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        $this->withdrawals->record($childId, $refusing, [$optionId]);

        (new \Database\Seeders\ChildOptionWithdrawalsSeeder)->run();

        $this->assertFalse(
            DB::table('category_child_option')->where('child_id', $childId)
                ->whereIn('category_id', [ChildOptionWithdrawals::ALL_ROOTS, $refusing])
                ->where('option_id', $optionId)->exists(),
            'the root that refused still carries it'
        );

        $this->assertTrue(
            DB::table('category_child_option')->where('child_id', $childId)
                ->where('category_id', $other)->where('option_id', $optionId)->exists(),
            'a refusal under one root stripped the option from another'
        );
    }

    /** The capture is a measurement, so running it twice must find nothing new. */
    public function test_the_baseline_capture_is_idempotent(): void
    {
        $before = DB::table(ChildOptionWithdrawals::TABLE)->count();

        $this->artisan('taxonomy:capture-withdrawals')->assertSuccessful();

        $this->assertSame($before, DB::table(ChildOptionWithdrawals::TABLE)->count());
    }
}
