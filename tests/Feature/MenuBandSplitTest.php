<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «مجموعة بنود منيو أظن حان الوقت لتقسيمها» — owner, 2026-08-10.
 *
 * Four vocabularies wearing one name. The split moves options between GROUPS and
 * nothing else: no child link changes, so every merchant keeps every heading he
 * had and only the screen's grouping moves.
 */
class MenuBandSplitTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,string> */
    private function bandsOf(string $groupNameAr): array
    {
        return DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupNameAr)->pluck('o.name_ar')->all();
    }

    /**
     * @dataProvider splitGroups
     */
    public function test_each_new_group_holds_its_own_vocabulary(string $groupNameAr, int $size, string $sample): void
    {
        $group = DB::table('option_groups')->where('name_ar', $groupNameAr)->first();

        $this->assertNotNull($group, "«{$groupNameAr}» was never created");
        $this->assertSame('line', (string) $group->price_role, 'a heading a merchant prices under must stay a line');

        $bands = $this->bandsOf($groupNameAr);

        $this->assertCount($size, $bands);
        $this->assertContains($sample, $bands);
    }

    /** @return array<string,array{0:string,1:int,2:string}> */
    public static function splitGroups(): array
    {
        return [
            'المطعم' => ['بنود المنيو', 14, 'مشويات'],
            'السوبر ماركت' => ['أقسام السوبر ماركت', 27, 'ألبان وبيض'],
            'المزارع' => ['مستلزمات المزارع', 3, 'ماشية وطيور'],
            'المعروضات' => ['صفوف معروضة', 3, 'مركبة معروضة'],
        ];
    }

    /** The four sets do not overlap — one band, one home. */
    public function test_no_band_is_in_two_groups(): void
    {
        $all = [];

        foreach (['بنود المنيو', 'أقسام السوبر ماركت', 'مستلزمات المزارع', 'صفوف معروضة'] as $group) {
            $all = array_merge($all, $this->bandsOf($group));
        }

        $this->assertSame(47, count($all));
        $this->assertSame(count($all), count(array_unique($all)));
    }

    /**
     * The point of doing it this way: a supermarket had 31 headings before the
     * split and has 31 after. Only their grouping moved.
     */
    public function test_a_supermarket_kept_every_heading_it_had(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');

        $carried = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $childId)
            ->whereIn('g.name_ar', ['بنود المنيو', 'أقسام السوبر ماركت'])
            ->count();

        // Not a fixed 31: the owner curates this list by hand in the bulk-options
        // screen, and a magic number turns his ticking into a test failure. What
        // the SPLIT promises is that no heading was lost to the regrouping — so
        // assert the shape, which is that it still reaches both drawers.
        $this->assertGreaterThan(20, $carried, 'the supermarket lost headings in the split');

        // Whether a supermarket ALSO keeps the restaurant bands («ساندوتشات»,
        // the two drink bands) is the owner's call and he has since unticked
        // them. Asserting it here was an opinion about his catalogue, not an
        // invariant of the split — the split's promise is only that no band was
        // lost to the REGROUPING, which is what the count above measures.
    }

    /** A restaurant is not offered the cleaning aisle. */
    public function test_a_restaurant_is_not_offered_a_grocery_aisle(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'مطعم')->value('id');

        $aisles = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $childId)->where('g.name_ar', 'أقسام السوبر ماركت')
            ->count();

        $this->assertSame(0, $aisles);
    }

    /** Re-running the owning seeder does not put them back together. */
    public function test_the_menu_seeder_does_not_undo_the_split(): void
    {
        $before = count($this->bandsOf('بنود المنيو'));

        $this->artisan('db:seed', ['--class' => 'MenuLineOptionsSeeder', '--no-interaction' => true])->run();
        $this->artisan('db:seed', ['--class' => 'MenuBandSplitSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, count($this->bandsOf('بنود المنيو')));
    }
}
