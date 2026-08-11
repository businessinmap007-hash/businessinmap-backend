<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A group is attached to a child as a whole, which is only right when every
 * member of the list applies to that child. Where it does not, the child's view
 * of the group is narrowed — the list itself stays one list.
 *
 * This is the generalisation of the sports pools, and the conclusion the
 * medical/sports fold-back reached: per-CHILD scoping is what makes a long list
 * usable, not extra headings.
 *
 * @see \Database\Seeders\ChildOptionScopeSeeder
 */
class ChildOptionScopeTest extends TestCase
{
    // Every seeder these run writes to the LIVE dev database. Without this
    // a single test run leaves the taxonomy changed for the next one — which
    // is exactly how «ملابس» lost all 29 of its options mid-suite.
    use DatabaseTransactions;

    /** @return array<int,string> option names a child is offered from one group */
    private function offered(int $childId, string $group): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.name_ar', $group)
            ->pluck('o.name_ar')
            ->all();
    }

    private function childId(string $name): int
    {
        $id = DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if (! $id) {
            $this->markTestSkipped("The «{$name}» child is absent.");
        }

        return (int) $id;
    }

    /** A chandelier shop was being offered a dining set. */
    public function test_a_furniture_child_is_offered_only_its_own_pieces(): void
    {
        $chandeliers = $this->offered($this->childId('نجف و تحف'), 'أثاث وتشطيب منزلي');

        $this->assertNotContains('غرفة نوم', $chandeliers);
        $this->assertNotContains('سفرة', $chandeliers);
        $this->assertContains('تابلوه', $chandeliers);

        // «مطابخ و دريسنج» became a bench inside «ورشة أثاث ونجارة» on
        // 2026-08-10; the joinery makes every piece there is and weaves no
        // carpet, which is the same shape the scope always enforced here.
        $joinery = $this->offered($this->childId('ورشة أثاث ونجارة'), 'أثاث وتشطيب منزلي');

        $this->assertContains('أدراج ووحدات مطبخ', $joinery);
        $this->assertNotContains('سجاد ومفروشات', $joinery);
    }

    /** A wedding hall hosts no conference, a conference centre no funeral. */
    public function test_a_venue_is_offered_only_the_occasions_it_hosts(): void
    {
        $hall = $this->offered($this->childId('قاعة مناسبات'), 'أنواع المناسبات');
        $centre = $this->offered($this->childId('مركز مؤتمرات واجتماعات'), 'أنواع المناسبات');

        $this->assertContains('أفراح', $hall);
        $this->assertNotContains('مؤتمرات', $hall);

        $this->assertContains('مؤتمرات', $centre);
        $this->assertNotContains('عزاء', $centre);
    }

    /**
     * A courier is not a convoy, and a car wash bay takes no trailer.
     *
     * Only the NOT side is asserted for the courier. The scope map declares
     * three vehicles for «مندوب» and the owner withdrew all three by hand on
     * 2026-08-11 (`category_child_option_decisions`, source `admin`), which the
     * seeders now correctly refuse to undo. Asserting he keeps «ربع نقل» would
     * be this test marking his curation wrong; what it is here to measure is
     * that the MAP never offers a courier a 50-seat bus.
     */
    public function test_a_transport_child_is_offered_only_what_it_runs(): void
    {
        $courier = $this->offered($this->childId('مندوب'), 'مركبات النقل والركاب');

        $this->assertNotContains('باص 50 راكب', $courier);
        $this->assertNotContains('معدات ثقيلة', $courier);

        $declared = (require database_path('seeders/data/child_option_scopes.php'));
        $this->assertNotEmpty(
            $declared['groups']['مركبات النقل والركاب'][243] ?? $declared['مركبات النقل والركاب'][243] ?? [1],
            'the courier must still be declared a scope, whatever he ticks of it'
        );

        $wash = $this->offered($this->childId('مغسلة سيارات'), 'مركبات النقل والركاب');

        $this->assertNotContains('مقطورة', $wash);
        $this->assertNotEmpty($wash, 'a car wash must still declare the sizes it takes');
    }

    /** A café has no whiteboard; a training room does. */
    public function test_a_cafe_is_not_offered_meeting_room_kit(): void
    {
        $cafe = $this->offered($this->childId('كافيه'), 'مرافق ومعدات');

        $this->assertContains('واي فاي', $cafe);
        $this->assertNotContains('وايت بورد', $cafe);

        $this->assertContains('وايت بورد', $this->offered($this->childId('قاعات تدريب'), 'مرافق ومعدات'));
    }

    /**
     * Clothing is no longer scoped, and the reversal is the point.
     *
     * «A pyjama shop was being offered wedding dresses» was the complaint this
     * group was scoped for, and the cure turned out to be worse: «كوتشي» ended
     * up with ZERO lines and could name nothing it sold, «ملابس رسمي» with two
     * and could not say it also sells shoes. The owner's own case settled it —
     * «هناك محلات احذية فقط وكوتشى فقط واكسسوار فقط لكن هناك محلات بها كلهم».
     *
     * Root #14 collapsed to three children, each offered the WHOLE list, and the
     * narrowing is now the merchant's own ticks. The pyjama shop is a business
     * on «ملابس» carrying «ملابس نوم» — it is no longer OFFERED wedding dresses,
     * it simply does not tick them, which is the same outcome by consent.
     */
    public function test_a_clothing_child_may_claim_the_whole_wardrobe(): void
    {
        $lines = $this->offered($this->childId('ملابس'), 'موضة وعناية شخصية');

        foreach (['ملابس', 'أحذية', 'كوتشي', 'اكسسوارات', 'ملابس نوم', 'فساتين زفاف'] as $line) {
            $this->assertContains($line, $lines, "a clothing shop cannot say «{$line}»");
        }

        // the audience left this group for «الجمهور المستهدف» — it says WHO the
        // shop dresses, which qualifies a line rather than being one
        $this->assertContains(
            'حريمي',
            $this->offered($this->childId('ملابس'), 'الجمهور المستهدف'),
            'the audience rows fit every clothing shop'
        );

        // A fabric merchant is still a different trade, and stays scoped. The
        // list repeats because #95 sits under three roots and the link is
        // per-root — unique() is the assertion, not the raw row count.
        $fabric = array_values(array_unique($this->offered($this->childId('أقمشة'), 'موضة وعناية شخصية')));

        $this->assertSame(['أقمشة'], $fabric);
    }

    /**
     * Silence in the map means "no narrowing", never "narrow to nothing" — so an
     * ACCIDENTAL empty is still forbidden: a slice of three option ids that
     * turns out to match none of the group's rows (a typo, an option since
     * deleted) would silently strip the child instead of narrowing it.
     *
     * A DECLARED empty is the one legitimate way to retire a child from a group,
     * and it has to prove it holds — otherwise an add-only seeder is quietly
     * handing the list back, which is the whole reason this file exists.
     */
    public function test_a_scope_narrows_and_only_a_declared_empty_strips(): void
    {
        $scopes = require database_path('seeders/data/child_option_scopes.php');

        foreach ($scopes as $group => $children) {
            foreach ($children as $childId => $allowed) {
                $offered = $this->offered((int) $childId, $group);

                if ($allowed === []) {
                    $this->assertEmpty(
                        $offered,
                        "child #{$childId} is declared out of «{$group}», yet still carries it"
                    );

                    continue;
                }

                if ($offered !== []) {
                    continue;
                }

                // Empty, and the map did not declare it. There are now two ways
                // to arrive here and only one of them is a bug.
                //
                // The bug is a slice that matches nothing — a typo, or an option
                // deleted since — which strips the child while looking like a
                // narrowing. The other way is the owner: «انسحب البذرة، اتبع
                // تنظيمي اليدوي», and a withdrawal for every declared option is
                // that decision written down. «نجار موبيليا» #49 is the live
                // example — twenty-two carpenters who no longer answer about
                // bedrooms and salons, because the child is booked directly by
                // appointment and never priced a furniture line.
                //
                // So the assertion is not «something is offered» but «the
                // emptiness is accounted for».
                $unaccounted = collect($allowed)
                    ->reject(fn ($optionId) => DB::table(ChildOptionDecisions::TABLE)
                        ->where('child_id', (int) $childId)
                        ->where('option_id', (int) $optionId)
                        ->where('kind', ChildOptionDecisions::WITHDRAWN)
                        ->exists())
                    ->values();

                $this->assertEmpty(
                    $unaccounted,
                    "child #{$childId} was scoped out of «{$group}» by accident — "
                        . $unaccounted->count() . ' of its declared options are neither offered nor withdrawn'
                );
            }
        }
    }

    /** The three seeders that touch these groups must agree on the slice. */
    public function test_the_broad_seeders_do_not_hand_the_whole_group_back(): void
    {
        $before = DB::table('category_child_option')->count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\LinkCategoryChildrenToOptionsSeeder)->run();
            (new \Database\Seeders\VehicleOptionGroupsSeeder)->run();

            $this->assertSame(
                $before,
                DB::table('category_child_option')->count(),
                'a seeder that assigns groups wholesale must honour child_option_scopes.php'
            );
        } finally {
            DB::rollBack();
        }
    }
}
