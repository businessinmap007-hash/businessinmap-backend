<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionDecisions;
use App\Services\CategoryChildOptionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «انسحب البذرة، اتبع تنظيمي اليدوي» · «ثبّت الإضافات اليدوية أيضًا»
 * — owner, 2026-08-10.
 *
 * A seeder's file is not the only thing that knows what a child should offer,
 * and until this table existed it behaved as though it were. The add-only
 * seeders could not tell «never granted» from «granted then removed», so they
 * restored what he unticked — the whole furniture list onto twenty-two
 * carpenters, freight terms onto a café, sandwiches onto a supermarket. The
 * replace-style ones could not tell «declared by me» from «added by him», so
 * they dropped what he ticked. Two failures, one cause.
 *
 * These tests pin both directions and the reversal. The reversal matters most:
 * a mechanism that cannot be undone is worse than the problem it solves, so
 * every row here costs one click to change and the two kinds are a toggle.
 */
class ChildOptionDecisionTest extends TestCase
{
    use DatabaseTransactions;

    private ChildOptionDecisions $decisions;

    private CategoryChildOptionScope $scope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decisions = app(ChildOptionDecisions::class);
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
            DB::table(ChildOptionDecisions::TABLE)
                ->where('child_id', $childId)->where('category_id', $rootId)
                ->where('option_id', $optionId)->exists(),
            'the removal left no record, so the next seeder run will undo it'
        );

        $this->assertContains($optionId, $this->decisions->idsFor($childId, $rootId)->all());
    }

    /** And a seeder asking what it may grant is told no. */
    public function test_a_withdrawn_option_is_filtered_out_of_a_grant(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        $this->assertSame([$optionId], $this->decisions->filter($childId, $rootId, [$optionId]));

        $this->decisions->record($childId, $rootId, [$optionId]);

        $this->assertSame([], $this->decisions->filter($childId, $rootId, [$optionId]));

        // A shared grant reaches every root, so one root saying no is enough.
        $this->assertSame([], $this->decisions->filter($childId, 0, [$optionId]));
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

        $this->decisions->record($childId, $rootId, [$optionId]);
        $this->scope->grantFor($childId, $rootId, [$optionId]);

        $this->assertSame(
            [],
            $this->decisions->idsFor($childId, $rootId)->intersect([$optionId])->all(),
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

        $this->decisions->record($childId, ChildOptionDecisions::ALL_ROOTS, [$optionId]);

        $this->scope->grantFor($childId, $mine, [$optionId]);

        $this->assertSame([$optionId], $this->decisions->filter($childId, $mine, [$optionId]));
        $this->assertSame([], $this->decisions->filter($childId, $other, [$optionId]));
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
        $this->decisions->record($childId, ChildOptionDecisions::ALL_ROOTS, [$optionId]);

        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => ChildOptionDecisions::ALL_ROOTS,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        (new \Database\Seeders\ChildOptionDecisionsSeeder)->run();

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
            'category_id' => ChildOptionDecisions::ALL_ROOTS,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        $this->decisions->record($childId, $refusing, [$optionId]);

        (new \Database\Seeders\ChildOptionDecisionsSeeder)->run();

        $this->assertFalse(
            DB::table('category_child_option')->where('child_id', $childId)
                ->whereIn('category_id', [ChildOptionDecisions::ALL_ROOTS, $refusing])
                ->where('option_id', $optionId)->exists(),
            'the root that refused still carries it'
        );

        $this->assertTrue(
            DB::table('category_child_option')->where('child_id', $childId)
                ->where('category_id', $other)->where('option_id', $optionId)->exists(),
            'a refusal under one root stripped the option from another'
        );
    }

    /**
     * The symmetric half: «ثبّت الإضافات اليدوية أيضًا».
     *
     * Ticking an option ON is a decision too. Without a pin, the replace-style
     * seeders drop whatever their file does not declare, so a hand addition
     * survived exactly until the next run.
     */
    public function test_a_hand_addition_is_pinned(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        $this->scope->grantFor($childId, $rootId, [$optionId]);

        $this->assertContains(
            $optionId,
            $this->decisions->idsFor($childId, $rootId, ChildOptionDecisions::PINNED)->all(),
            'the addition left no record, so the next seeder run will drop it'
        );

        // And a replace-style seeder asking what it may drop is told no.
        $this->assertSame([], $this->decisions->droppable($childId, $rootId, [$optionId]));
    }

    /**
     * Pinned and withdrawn are a TOGGLE, never both at once. If they could
     * coexist the two families of seeder would read the same table and disagree
     * forever, each of them correctly.
     */
    public function test_the_two_decisions_cannot_both_stand(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        $this->decisions->pin($childId, $rootId, [$optionId]);
        $this->decisions->record($childId, $rootId, [$optionId]);

        $this->assertSame([], $this->decisions->idsFor($childId, $rootId, ChildOptionDecisions::PINNED)->all());
        $this->assertContains($optionId, $this->decisions->idsFor($childId, $rootId)->all());

        // …and back the other way, because the last decision is the one he meant.
        $this->decisions->pin($childId, $rootId, [$optionId]);

        $this->assertSame([], $this->decisions->idsFor($childId, $rootId)->all());
        $this->assertContains($optionId, $this->decisions->idsFor($childId, $rootId, ChildOptionDecisions::PINNED)->all());
    }

    /**
     * The whole point, end to end: an option the owner ticked on that no seeder
     * declares survives a run of every seeder that would have dropped it.
     *
     * @dataProvider replaceStyleSeeders
     */
    public function test_a_replace_style_seeder_does_not_drop_a_pinned_option(string $seeder): void
    {
        // «سوبر ماركت» and a menu band its map does not give it — the exact
        // shape of the supermarket that kept losing «ساندوتشات».
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');
        $optionId = $this->freeOption($childId);

        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => ChildOptionDecisions::ALL_ROOTS,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        $this->decisions->pin($childId, ChildOptionDecisions::ALL_ROOTS, [$optionId]);

        (new $seeder)->run();

        $this->assertTrue(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            class_basename($seeder) . ' dropped an option the owner pinned'
        );
    }

    /** @return array<string,array{0:class-string}> */
    public static function replaceStyleSeeders(): array
    {
        return [
            'scopes' => [\Database\Seeders\ChildOptionScopeSeeder::class],
            'groups' => [\Database\Seeders\ChildOptionGroupsSeeder::class],
            'menu bands' => [\Database\Seeders\MenuLineOptionsSeeder::class],
            'vehicles' => [\Database\Seeders\VehicleOptionGroupsSeeder::class],
        ];
    }

    /** The backstop restores a pinned link one of the other 36 seeders dropped. */
    public function test_the_reconciliation_pass_restores_a_link_a_seeder_dropped(): void
    {
        $childId = $this->childWithRoots();
        $optionId = $this->freeOption($childId);

        $this->decisions->pin($childId, ChildOptionDecisions::ALL_ROOTS, [$optionId]);

        // The pin is recorded and the row is NOT there — a seeder deleted it.
        $this->assertFalse(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists()
        );

        (new \Database\Seeders\ChildOptionDecisionsSeeder)->run();

        $this->assertTrue(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            'the backstop left a pinned option on the floor'
        );
    }

    /** Restoring must not duplicate a row a shared grant already covers. */
    public function test_the_backstop_does_not_duplicate_a_shared_row(): void
    {
        $childId = $this->childWithRoots();
        $rootId = $this->rootsOf($childId)[0];
        $optionId = $this->freeOption($childId);

        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => ChildOptionDecisions::ALL_ROOTS,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        $this->decisions->pin($childId, $rootId, [$optionId]);

        (new \Database\Seeders\ChildOptionDecisionsSeeder)->run();

        $this->assertSame(
            1,
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->count(),
            'the shared row already reached this root and the backstop added a second'
        );
    }

    /** The capture is a measurement, so running it twice must find nothing new. */
    public function test_the_baseline_capture_is_idempotent(): void
    {
        $before = DB::table(ChildOptionDecisions::TABLE)->count();

        $this->artisan('taxonomy:capture-withdrawals')->assertSuccessful();

        $this->assertSame($before, DB::table(ChildOptionDecisions::TABLE)->count());
    }
}
