<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
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

        $kitchens = $this->offered($this->childId('مطابخ و دريسنج'), 'أثاث وتشطيب منزلي');

        $this->assertContains('أدراج ووحدات مطبخ', $kitchens);
        $this->assertNotContains('سجاد ومفروشات', $kitchens);
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

    /** A courier is not a convoy, and a car wash bay takes no trailer. */
    public function test_a_transport_child_is_offered_only_what_it_runs(): void
    {
        $courier = $this->offered($this->childId('مندوب'), 'مركبات النقل والركاب');

        $this->assertNotContains('باص 50 راكب', $courier);
        $this->assertNotContains('معدات ثقيلة', $courier);
        $this->assertContains('ربع نقل', $courier);

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

    /** A pyjama shop was being offered wedding dresses. */
    public function test_a_clothing_child_is_offered_only_its_own_lines(): void
    {
        $pyjamas = $this->offered($this->childId('ملابس النوم'), 'موضة وعناية شخصية');

        $this->assertNotContains('فساتين زفاف', $pyjamas);
        $this->assertContains('ملابس', $pyjamas);

        // the audience left this group for «الجمهور المستهدف» — it says WHO the
        // shop dresses, which qualifies a line rather than being one
        $this->assertContains(
            'حريمي',
            $this->offered($this->childId('ملابس النوم'), 'الجمهور المستهدف'),
            'the audience rows fit every clothing shop'
        );

        $this->assertContains('فساتين زفاف', $this->offered($this->childId('ملابس زفاف'), 'موضة وعناية شخصية'));
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

                $this->assertNotEmpty(
                    $offered,
                    "child #{$childId} was scoped out of «{$group}» entirely"
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
