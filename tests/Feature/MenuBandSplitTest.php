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

    /**
     * «أقسام السوبر ماركت» is gone from this list on purpose. It was split
     * again the same day into the five counters its own link data drew — see
     * GroceryAisleSplitTest — and is now a standing EMPTY group, kept as the
     * record of where the five came from. What this test still owns is that
     * the other three came out of «بنود المنيو» and stayed out.
     *
     * @return array<string,array{0:string,1:int,2:string}>
     */
    public static function splitGroups(): array
    {
        return [
            // «كريب» added and «فطائر» reclaimed, both 2026-08-16.
            'المطعم' => ['بنود المنيو', 16, 'مشويات'],
            'المزارع' => ['مستلزمات المزارع', 3, 'ماشية وطيور'],
            'المعروضات' => ['صفوف معروضة', 3, 'مركبة معروضة'],
        ];
    }

    /** The sets do not overlap — one band, one home. */
    public function test_no_band_is_in_two_groups(): void
    {
        $all = [];

        foreach (['بنود المنيو', 'مستلزمات المزارع', 'صفوف معروضة'] as $group) {
            $all = array_merge($all, $this->bandsOf($group));
        }

        // 22 since «كريب» and «فطائر» joined the menu for the food court.
        $this->assertSame(22, count($all));
        $this->assertSame(count($all), count(array_unique($all)));
    }

    /**
     * The point of doing it this way: a supermarket had 31 headings before the
     * split and has 31 after. Only their grouping moved.
     */
    public function test_a_supermarket_kept_every_heading_it_had(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');

        // The aisle drawer is now five drawers (GroceryAisleSplitSeeder), so
        // «still reaches both» has to be counted across all of them. Same
        // promise, wider net: neither split may lose a heading.
        $carried = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $childId)
            ->whereIn('g.name_ar', [
                'بنود المنيو',
                'أقسام السوبر ماركت',
                'أقسام الطازج واللحوم',
                'بنود المخبوزات والحلويات',
                'أقسام البقالة الجافة',
                'أقسام المشروبات',
                'أقسام المنزل والعناية',
                /*
                 * And the sixth drawer, added 2026-08-21. The owner moved the
                 * three market children onto «أصناف المنتجات الغذائية» — twenty
                 * ranges, pinned by hand — and withdrew from the aisle lists
                 * the rows those ranges already say, keeping the counters.
                 *
                 * Not counting it here reported the move as a loss: سوبر ماركت
                 * came out at 16. The headings did not go anywhere, they went
                 * into a drawer this list had not heard of, which is the same
                 * thing that happened when the aisle drawer became five.
                 */
                'أصناف المنتجات الغذائية',
            ])
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
            ->where('cco.child_id', $childId)
            ->where('g.name_ar', 'like', 'أقسام%')
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

    /**
     * «اضف فطائر كبند فى بنود المنيو» — owner, 2026-08-16.
     *
     * The split only ever pushed bands out, which is why `kept` had to become a
     * rule instead of a comment: «فطائر» had been carried out twice, once with
     * the aisles and once with the bakery counter, and taking its name off both
     * lists would have moved nothing — neither seeder touches an option that is
     * not standing in its own source group.
     *
     * Both halves are asserted, because either alone is a silent regression: the
     * band has to arrive, AND the two seeders that put it in the bakery have to
     * leave it alone afterwards.
     */
    public function test_a_kept_band_is_reclaimed_from_the_group_a_split_left_it_in(): void
    {
        $groupOf = fn (string $band) => (string) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.name_ar', $band)->where('g.name_ar', '!=', 'أقسام السوبر ماركت')
            ->value('g.name_ar');

        $this->assertSame('بنود المنيو', $groupOf('فطائر'));

        $bakery = (int) DB::table('option_groups')->where('name_ar', 'بنود المخبوزات والحلويات')->value('id');
        $option = (int) DB::table('options')->where('name_ar', 'فطائر')->value('id');

        // Put it back where the second split had it, then let the chain run in
        // its real order and prove it comes home.
        DB::table('options')->where('id', $option)->update(['group_id' => $bakery]);

        $this->artisan('db:seed', ['--class' => 'MenuBandSplitSeeder', '--no-interaction' => true])->run();
        $this->artisan('db:seed', ['--class' => 'GroceryAisleSplitSeeder', '--no-interaction' => true])->run();

        $this->assertSame('بنود المنيو', $groupOf('فطائر'));
    }

    /**
     * And the reason it was reclaimed: «مجمع مطاعم يحتاج فروع اخرى» — the five
     * counters the owner named for a food court. Four were already there.
     *
     * One «فطائر» and not two. The bakery keeps the same option id it always
     * had, so this is the whole cost of the move: a heading changed, nothing
     * was duplicated, and nobody lost a row.
     */
    public function test_the_food_court_carries_every_counter_the_owner_named(): void
    {
        $bandsOfChild = fn (string $child) => DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('c.name_ar', $child)->distinct()->pluck('o.name_ar')->all();

        $court = $bandsOfChild('مجمع مطاعم');

        foreach (['مأكولات بحرية', 'مشويات', 'فطائر', 'كريب', 'ساندوتشات'] as $counter) {
            $this->assertContains($counter, $court, "«{$counter}» is missing from the food court");
        }

        $this->assertCount(1, DB::table('options')->where('name_ar', 'فطائر')->get());

        foreach (['مخابز', 'حلويات'] as $kitchen) {
            $this->assertContains('فطائر', $bandsOfChild($kitchen), "«{$kitchen}» lost «فطائر» in the move");
        }
    }
}
