<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «راجع أصناف المنتجات الغذائية و أقسام السوبر ماركت و بنود المنيو … راجع
 * التكرار والتشابه بينهم وأعد تقسيمهم» — owner, 2026-08-10.
 *
 * «أقسام السوبر ماركت» was five counters in one list, and nobody designed it
 * that way: every one of its 27 options was carried by exactly one of five
 * carrier sets, with the three general markets on top of all five. A
 * fishmonger, a bakery, a coffee merchant, a juice bar and a cleaning-supplies
 * shop had each answered a different part and ignored the rest.
 *
 * The split follows those lines. Only `options.group_id` moves, so no merchant
 * loses a heading — which is the property that makes it safe against a live
 * database the owner is editing while the tests run.
 */
class GroceryAisleSplitTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The five the split produced. Four are «أقسام» — a shelf a grocer stocks —
     * and one is «بنود», a counter somebody works at, because «المخابز
     * والحلويات مطابخ» (owner, 2026-08-10) and those two are what that group was
     * built around.
     */
    private const COUNTERS = [
        'أقسام الطازج واللحوم',
        'بنود المخبوزات والحلويات',
        'أقسام البقالة الجافة',
        'أقسام المشروبات',
        'أقسام المنزل والعناية',
    ];

    /** @return array<int,string> */
    private function optionsOf(string $groupNameAr): array
    {
        return DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupNameAr)
            ->pluck('o.name_ar')
            ->all();
    }

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('c.name_ar', $nameAr)
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM users u WHERE u.category_child_id = c.id)'))
            ->value('c.id');
    }

    /** @return array<int,string> group names this child is offered */
    private function groupsOffered(string $childNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($childNameAr))
            ->distinct()
            ->pluck('g.name_ar')
            ->all();
    }

    /**
     * An aisle heading is what a grocer PRICES under. مني ماركت and هايبر
     * ماركت carry `menu` and no `retail` at all, so the heading is the priced
     * row for them exactly as «مشويات» is for a restaurant.
     *
     * @dataProvider aisleGroups
     */
    public function test_each_counter_is_a_priced_heading(string $groupNameAr, int $size, string $sample): void
    {
        $group = DB::table('option_groups')->where('name_ar', $groupNameAr)->first();

        $this->assertNotNull($group, "«{$groupNameAr}» was never created");
        $this->assertSame('line', (string) $group->price_role);
        $this->assertSame(1, (int) $group->is_active);

        $options = $this->optionsOf($groupNameAr);

        $this->assertCount($size, $options);
        $this->assertContains($sample, $options);
    }

    /** @return array<string,array{0:string,1:int,2:string}> */
    public static function aisleGroups(): array
    {
        return [
            'الطازج' => ['أقسام الطازج واللحوم', 9, 'لحوم ودواجن'],
            'المخبوزات' => ['بنود المخبوزات والحلويات', 5, 'فطائر'],
            // Seven since «بن وشاي» was added on the owner's «بن يبيع حبوب فقط».
            'البقالة' => ['أقسام البقالة الجافة', 7, 'بن وشاي'],
            'المشروبات' => ['أقسام المشروبات', 2, 'عصائر'],
            'المنزل' => ['أقسام المنزل والعناية', 6, 'منظفات'],
        ];
    }

    /**
     * The point of the whole slice: a shop is offered its own counter and
     * nothing else. Before the split each of these saw all 27.
     *
     * @dataProvider specialists
     */
    public function test_a_specialist_sees_only_its_own_counter(string $childNameAr, string $expected): void
    {
        $offered = array_intersect($this->groupsOffered($childNameAr), self::COUNTERS);

        $this->assertSame([$expected], array_values($offered));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function specialists(): array
    {
        return [
            'سمّاك' => ['أسماك', 'أقسام الطازج واللحوم'],
            'مخبز' => ['مخابز', 'بنود المخبوزات والحلويات'],
            'محل منظفات' => ['منظفات', 'أقسام المنزل والعناية'],
            'مجمدات' => ['مجمدات', 'أقسام الطازج واللحوم'],
            'محل بن' => ['بن', 'أقسام البقالة الجافة'],
        ];
    }

    /**
     * «بن يبيع حبوب فقط، عصائر مطبخ» — owner, 2026-08-10.
     *
     * The two were the one case the split could not decide, because nothing in
     * the data distinguishes a shop from a kitchen: both sat on the drinks
     * aisle AND the menu's hot/cold bands, and both were plausible either way.
     * The answer went opposite ways, which is why it was asked rather than
     * guessed.
     *
     * A shop stocks and a kitchen prepares. «عصائر» as an aisle is a fridge of
     * bottles; as a menu band it is a man with a blender.
     */
    public function test_the_coffee_shop_stocks_and_the_juice_bar_cooks(): void
    {
        $bandsOf = fn (string $child, string $group) => DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($child))
            ->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();

        // The shop: aisles, no menu.
        $this->assertSame([], $bandsOf('بن', 'بنود المنيو'), '«بن» is still offered a kitchen heading');
        $this->assertSame([], $bandsOf('بن', 'أقسام المشروبات'), '«بن» still stocks bottled drinks');

        // …and it can name the one thing it sells, which the aisle list had no
        // word for until this ruling forced the question.
        $this->assertContains('بن وشاي', $bandsOf('بن', 'أقسام البقالة الجافة'));

        // The kitchen: menu, no aisle.
        $this->assertSame([], $bandsOf('عصائر', 'أقسام المشروبات'), '«عصائر» is still stocking a shelf');

        $this->assertSame(
            ['مشروبات ساخنة', 'مشروبات باردة'],
            $bandsOf('عصائر', 'بنود المنيو'),
            '«عصائر» lost the bands it prepares'
        );
    }

    /**
     * «المني والهايبر بقالة مش مطاعم» — owner, 2026-08-10.
     *
     * The three general markets are one trade at three sizes and they had
     * stopped agreeing: he took «ساندوتشات» and the two drink bands off «سوبر
     * ماركت» by hand and the other two kept them, so the platform held two
     * answers to whether a grocer runs a deli counter.
     *
     * They are not silent on drinks — «عصائر» and «مشروبات» are still theirs as
     * AISLES. What went is the claim to serve a cup, which is the same
     * distinction «بن» and «عصائر» were ruled on.
     */
    public function test_the_three_markets_agree_that_a_grocer_is_not_a_kitchen(): void
    {
        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $name) {
            $childId = $this->childId($name);

            $bands = DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('cco.child_id', $childId)->where('g.name_ar', 'بنود المنيو')
                ->distinct()->pluck('o.name_ar')->all();

            $this->assertSame([], $bands, "«{$name}» is still offered a kitchen heading");

            $this->assertContains(
                'أقسام المشروبات',
                $this->groupsOffered($name),
                "«{$name}» lost drinks altogether instead of moving them to the aisle"
            );
        }
    }

    /** …and a general market still sees all five, because it really does stock them. */
    public function test_a_supermarket_still_sees_every_counter(): void
    {
        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $name) {
            $offered = array_intersect($this->groupsOffered($name), self::COUNTERS);

            $this->assertSame(
                self::COUNTERS,
                array_values(array_intersect(self::COUNTERS, $offered)),
                "«{$name}» lost a counter in the split"
            );
        }
    }

    /**
     * A fishmonger was reaching «مأكولات بحرية» in «بنود المنيو» — a RESTAURANT
     * heading meaning a cooked dish — to say it sells fish. It has an aisle of
     * its own now, and the menu row went back to the kitchens.
     */
    public function test_the_fishmongers_left_the_restaurant_menu(): void
    {
        $this->assertContains('أسماك ومأكولات بحرية طازجة', $this->optionsOf('أقسام الطازج واللحوم'));

        foreach (['أسماك', 'مزارع سمكية'] as $name) {
            $this->assertContains(
                'أسماك ومأكولات بحرية طازجة',
                $this->optionNamesOf($name),
                "«{$name}» cannot say it sells fish"
            );
        }

        // And the menu band still belongs to the people who cook it.
        $this->assertContains('مأكولات بحرية', $this->optionNamesOf('مطعم'));
    }

    /** @return array<int,string> */
    private function optionNamesOf(string $childNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $this->childId($childNameAr))
            ->distinct()
            ->pluck('o.name_ar')
            ->all();
    }

    /**
     * The duplicate that was a duplicate rather than a split: «أصناف المنتجات
     * الغذائية» restated the aisle list a third time. It survives for the three
     * children with no market list — a wholesaler answering «which ranges do
     * you deal in» — and is gone from the five that were asked twice.
     */
    public function test_the_stock_range_modifier_is_only_for_traders_without_a_market_list(): void
    {
        foreach (['مواد غذائية', 'استيراد وتصدير'] as $name) {
            $this->assertContains(
                'أصناف المنتجات الغذائية',
                $this->groupsOffered($name),
                "«{$name}» lost the only list it had"
            );
        }

        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت', 'مجمدات'] as $name) {
            $this->assertNotContains(
                'أصناف المنتجات الغذائية',
                $this->groupsOffered($name),
                "«{$name}» is still asked the same question twice"
            );
        }
    }

    /**
     * The rule that actually matters: no CHILD is asked the same word twice.
     *
     * Not «no word exists in two groups», which was the first version of this
     * test and was wrong. «معلبات» and «زيوت وسمن» genuinely live in two: once
     * as a priced aisle heading for a grocer, and once as a stock-range
     * modifier for a wholesaler with no market list. Two different questions
     * that happen to share a noun.
     *
     * What was broken was five children holding BOTH — the supermarket asked
     * to price «معلبات» and, further down the same screen, to tick it. That is
     * what this guards, and it is measured per child rather than per group
     * because the group overlap is a fact about Arabic, not a defect.
     */
    public function test_no_child_is_asked_the_same_word_twice(): void
    {
        $groups = array_merge(self::COUNTERS, [
            'بنود المنيو', 'أصناف المنتجات الغذائية', 'أقسام السوبر ماركت',
        ]);

        $rows = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->whereIn('g.name_ar', $groups)
            ->distinct()
            ->get(['c.name_ar as child', 'o.name_ar as word', 'g.name_ar as grp']);

        $seen = [];

        foreach ($rows as $row) {
            $seen[$row->child][$row->word][$row->grp] = true;
        }

        $doubled = [];

        foreach ($seen as $child => $words) {
            foreach ($words as $word => $groupsHolding) {
                if (count($groupsHolding) > 1) {
                    $doubled[] = "«{$child}» → «{$word}» (" . implode(' + ', array_keys($groupsHolding)) . ')';
                }
            }
        }

        $this->assertSame([], $doubled, "asked twice:\n  " . implode("\n  ", $doubled));
    }

    /**
     * The parent is left standing and empty rather than deleted — nothing in
     * this taxonomy is deleted, and an empty group is the clearest record of
     * where the five came from.
     */
    public function test_the_parent_is_left_standing_and_empty(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'أقسام السوبر ماركت')->first();

        $this->assertNotNull($group, 'the parent group was deleted');
        $this->assertSame([], $this->optionsOf('أقسام السوبر ماركت'));
    }

    /** Re-running moves nothing and creates nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'GroceryAisleSplitSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
