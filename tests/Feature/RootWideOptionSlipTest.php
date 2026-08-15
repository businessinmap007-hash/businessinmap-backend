<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «هناك خطا حدث عند تعديل خيارات زراعية وحيوانية واخدت كل خيارات الرياضة بشكل
 * غريب».
 *
 * At 2026-08-13 23:38:25 one bulk save handed every child under «زراعية
 * وحيوانية» the same twenty swimming-club options — سباحة، غوص، ساونا،
 * جاكوزي، مدرب شخصي — and pulled each child's own vocabulary out in the same
 * instant: tractors, ploughs, drip irrigation, poultry states, livestock kinds.
 *
 * It was reversible only because the decisions ledger records BOTH directions
 * of a save, one row per option: `pinned` for what was granted, `withdrawn` for
 * what was removed. `bim:revert-option-save` reads that and undoes both, links
 * and ledger rows together — a decision left behind is a standing order, so
 * reverting the links alone leaves the grant blocked and the removal permanent.
 *
 * What is asserted here is the SHAPE the mistake had, not that one root is
 * clean: a vocabulary belongs to a family of trades, and a group that reaches
 * every child of a root it has no business in is the fingerprint.
 */
class RootWideOptionSlipTest extends TestCase
{
    use DatabaseTransactions;

    /** Groups that describe a sports venue and nothing a farm sells. */
    private const SPORTS_GROUPS = ['الأنشطة الرياضية', 'مرافق النادي الرياضي'];

    private function rootId(string $name): int
    {
        return (int) DB::table('categories')->where('name_ar', $name)->value('id');
    }

    /** The reported case, held closed. */
    public function test_no_farm_child_offers_a_swimming_pool(): void
    {
        $rootId = $this->rootId('زراعية وحيوانية');

        if (! $rootId) {
            $this->markTestSkipped('The agriculture root is gone.');
        }

        $offenders = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_parent_child as pc', 'pc.child_id', '=', 'cco.child_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('pc.parent_id', $rootId)
            ->whereIn('g.name_ar', self::SPORTS_GROUPS)
            ->pluck('c.name_ar')
            ->unique();

        $this->assertSame(
            [],
            $offenders->values()->all(),
            'a sports vocabulary is back on the farm shelf'
        );
    }

    /**
     * A football pitch does not teach yoga and a gym does not run a diving
     * course. Each of these children lost its own list to one flattening save
     * and got it back; what is held here is that they stay distinguishable.
     *
     * NOT asserted: «no save may cover a whole root». That rule was tried and
     * is false — the same shape flagged the الرياضة save, which legitimately
     * gave نادي صحي and أكاديمية رياضية their first vocabulary and withdrew
     * nothing from them. Root-wide curation is real; what is wrong is a root
     * whose children all end up saying the SAME thing.
     */
    public function test_the_sports_children_do_not_all_say_the_same_thing(): void
    {
        $rootId = $this->rootId('الرياضة');

        if (! $rootId) {
            $this->markTestSkipped('The sports root is gone.');
        }

        $byChild = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_parent_child as pc', 'pc.child_id', '=', 'cco.child_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('pc.parent_id', $rootId)
            ->where('g.name_ar', 'الأنشطة الرياضية')
            ->select('c.name_ar', 'o.name_ar as option_name')
            ->get()
            ->groupBy('name_ar')
            ->map(fn ($rows) => $rows->pluck('option_name')->sort()->values()->implode('|'));

        if ($byChild->count() < 2) {
            $this->markTestSkipped('Fewer than two children carry sports activities.');
        }

        $this->assertGreaterThan(
            1,
            $byChild->unique()->count(),
            'every child of the sports root offers an identical activity list — they were flattened onto one again'
        );

        // The two that were flattened, named, because they are the ones a
        // repeat would hit first.
        foreach (['ملاعب كرة' => 'كرة قدم', 'جيم' => 'كاراتيه'] as $child => $mustHave) {
            if (! $byChild->has($child)) {
                continue;
            }

            $this->assertStringContainsString(
                $mustHave,
                $byChild[$child],
                "«{$child}» lost «{$mustHave}» — the flattening is back"
            );
        }
    }

    /** The undo reads both directions out of the ledger, not one. */
    public function test_the_revert_tool_undoes_grants_and_removals_together(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/RevertChildOptionSave.php'));

        $this->assertStringContainsString(ChildOptionDecisions::PINNED, $source);
        $this->assertStringContainsString(ChildOptionDecisions::WITHDRAWN, $source);

        // …and clears the ledger rows, or the seeders keep enforcing the slip.
        $this->assertMatchesRegularExpression(
            '/DB::table\(ChildOptionDecisions::TABLE\)->whereIn\(.+\)->delete\(\)/',
            $source,
            'the decisions are left behind as standing orders'
        );
    }

    /** It writes nothing without --apply. */
    public function test_the_revert_tool_is_dry_by_default(): void
    {
        $rootId = $this->rootId('زراعية وحيوانية');

        $before = DB::table('category_child_option as cco')
            ->join('category_parent_child as pc', 'pc.child_id', '=', 'cco.child_id')
            ->where('pc.parent_id', $rootId)
            ->count();

        $this->artisan('bim:revert-option-save', [
            '--root' => $rootId,
            '--at' => '2026-08-13 23:38:25',
        ])->assertSuccessful();

        $after = DB::table('category_child_option as cco')
            ->join('category_parent_child as pc', 'pc.child_id', '=', 'cco.child_id')
            ->where('pc.parent_id', $rootId)
            ->count();

        $this->assertSame($before, $after, 'a dry run wrote to the taxonomy');
    }
}
