<?php

namespace Tests\Feature;

use Database\Seeders\ChildTradeVocabulariesSeeder;
use Database\Seeders\OptionPriceRolesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «هناك الكثير من الخيارات يحتاج اصناف مثل ما فعلت فى فواكة وخضروات»
 * — المالك، 2026-08-24.
 *
 * «أقسام الطازج واللحوم» holds nine COUNTERS — «لحوم ودواجن», «أسماك ومأكولات
 * بحرية طازجة», «ألبان وبيض», «أجبان» — and not one of them is a thing with a
 * price. The poultry and grain halves of the same trade already had their
 * varieties; these three lists are what the fishmonger and the butcher's
 * counter were missing.
 *
 * Rolls back.
 */
class FreshCounterVarietiesTest extends TestCase
{
    use DatabaseTransactions;

    private const MEAT = 'أنواع اللحوم';
    private const FISH = 'أنواع الأسماك والمأكولات البحرية';
    private const DAIRY = 'أنواع الألبان والأجبان';
    private const FARM = 'أنواع الثروة الحيوانية والسمكية';
    private const COUNTERS = 'أقسام الطازج واللحوم';

    /** @return array<string,mixed> */
    private function declared(): array
    {
        return (require base_path('database/seeders/data/shop_child_vocabularies.php'))['groups'];
    }

    /** @return \Illuminate\Support\Collection<int,string> */
    private function optionsOf(string $groupName)
    {
        return DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupName)
            ->pluck('o.name_ar');
    }

    /** @return \Illuminate\Support\Collection<int,string> */
    private function childrenOf(string $groupName)
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('g.name_ar', $groupName)
            ->distinct()
            ->pluck('c.name_ar');
    }

    /**
     * @dataProvider counters
     */
    public function test_a_counter_now_has_varieties_behind_it(string $group, string $counter, array $mustSay): void
    {
        $words = $this->optionsOf($group);

        $this->assertNotEmpty($words, "«{$group}» is empty — «{$counter}» is still a counter with nothing behind it.");

        foreach ($mustSay as $word) {
            $this->assertContains($word, $words->all(), "«{$group}» cannot say «{$word}»");
        }

        // The counter itself stays where it is: it says which fridge, and that
        // is still a true thing for a supermarket to say.
        $this->assertContains($counter, $this->optionsOf(self::COUNTERS)->all());
    }

    public static function counters(): array
    {
        return [
            'لحوم' => [self::MEAT, 'لحوم ودواجن', ['كندوز', 'ضاني', 'كبدة', 'مفروم']],
            'أسماك' => [self::FISH, 'أسماك ومأكولات بحرية طازجة', ['جمبري', 'كابوريا', 'دنيس', 'أسماك بلطي']],
            'ألبان' => [self::DAIRY, 'أجبان', ['جبنة رومي', 'جبنة قريش', 'زبادي', 'لبن']],
        ];
    }

    public function test_each_list_holds_exactly_what_its_file_declares(): void
    {
        $declared = $this->declared();

        foreach ([self::MEAT, self::FISH, self::DAIRY] as $group) {
            $this->assertEqualsCanonicalizing(
                array_keys($declared[$group]['options']),
                $this->optionsOf($group)->all(),
                "«{$group}»"
            );
        }
    }

    /**
     * The bug this file was written after: a row moved between groups while
     * the file that DECLARED it kept declaring it, so the seeder wrote it back
     * under «Tilapia (Agri)» on the next run — three times in one afternoon.
     */
    public function test_no_group_says_the_same_word_twice(): void
    {
        $doubled = DB::table('options')
            ->select('group_id', 'name_ar', DB::raw('COUNT(*) as n'))
            ->groupBy('group_id', 'name_ar')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertSame(
            [],
            $doubled->map(fn ($r) => $r->name_ar . ' ×' . $r->n)->all(),
            'A group saying one word twice is a data file that kept declaring what it gave away.'
        );
    }

    public function test_the_farm_fish_moved_and_were_not_cloned(): void
    {
        foreach (['أسماك بلطي', 'أسماك بوري', 'قراميط'] as $fish) {
            $rows = DB::table('options')->where('name_ar', $fish)->get(['id', 'group_id', 'name_en']);

            $this->assertCount(1, $rows, "«{$fish}» exists more than once — it was cloned, not moved.");

            $this->assertSame(
                self::FISH,
                DB::table('option_groups')->where('id', $rows[0]->group_id)->value('name_ar')
            );

            $this->assertStringNotContainsString(
                '(',
                (string) $rows[0]->name_en,
                'A suffixed English name means a duplicate was created around a unique key.'
            );
        }

        // …and the farm still has them: the links are by option id.
        $farm = DB::table('category_children_master')->where('name_ar', 'مزارع سمكية')->value('id');

        if ($farm) {
            $kept = DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->where('cco.child_id', $farm)
                ->whereIn('o.name_ar', ['أسماك بلطي', 'أسماك بوري', 'قراميط'])
                ->distinct()
                ->count('o.id');

            $this->assertSame(3, $kept, 'A fish farm that lost its tilapia has lost its trade.');
        }
    }

    public function test_the_land_livestock_list_kept_its_own(): void
    {
        $farm = $this->optionsOf(self::FARM);

        foreach (['أبقار', 'جاموس', 'أغنام', 'أسماك زينة', 'زريعة وإصبعيات'] as $word) {
            $this->assertContains($word, $farm->all());
        }

        foreach (['أسماك بلطي', 'قراميط'] as $moved) {
            $this->assertNotContains($moved, $farm->all(), 'An edible fish is the fishmonger\'s word now.');
        }
    }

    /**
     * @dataProvider carriers
     */
    public function test_each_list_reaches_the_children_that_carry_its_counter(string $group, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, $this->childrenOf($group)->all(), "«{$group}»");
    }

    public static function carriers(): array
    {
        /*
         * Widened 2026-08-24, twice in one day and both times by a decision:
         * the three markets were given the variety lists themselves («وتكون
         * الخيارات أقسام رئيسية … وتحتها كل الحبوب»), and «جزارة» was created
         * as the trade that sells meat.
         */
        return [
            'لحوم' => [self::MEAT, ['مجمدات', 'جزارة', 'هايبر ماركت', 'مني ماركت', 'سوبر ماركت']],
            'أسماك' => [self::FISH, ['أسماك', 'مجمدات', 'مزارع سمكية', 'هايبر ماركت', 'مني ماركت', 'سوبر ماركت']],
            'ألبان' => [self::DAIRY, ['مجمدات', 'هايبر ماركت', 'مني ماركت', 'سوبر ماركت']],
        ];
    }

    public function test_the_bakery_counter_has_its_bread(): void
    {
        $bread = $this->optionsOf('أنواع المخبوزات');

        foreach (['عيش بلدي', 'عيش فينو', 'فطير مشلتت', 'كحك'] as $word) {
            $this->assertContains($word, $bread->all());
        }

        $this->assertContains('مخابز', $this->childrenOf('أنواع المخبوزات')->all());
    }

    public function test_a_market_carries_the_counter_and_what_is_on_it(): void
    {
        // «وتكون الخيارات أقسام رئيسية مثل حبوب وغلال وتحتها كل الحبوب»
        $market = DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');

        $groups = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $market)
            ->distinct()
            ->pluck('g.name_ar');

        foreach ([
            'أقسام الطازج واللحوم',   // the counters it always had
            'الفواكه', 'الخضروات', self::MEAT, self::FISH, self::DAIRY,
            'أنواع المخبوزات', 'أنواع الحبوب والغلال', 'أنواع الدواجن والطيور',
        ] as $group) {
            $this->assertContains($group, $groups->all(), "«سوبر ماركت» cannot say «{$group}»");
        }
    }

    public function test_all_three_are_priceable_and_stay_priceable(): void
    {
        // The recorded landmine: a group missing from data/option_price_roles.php
        // is pushed back to `descriptive` on the next run.
        (new OptionPriceRolesSeeder())->run();

        foreach ([self::MEAT, self::FISH, self::DAIRY] as $group) {
            $this->assertSame(
                'line',
                DB::table('option_groups')->where('name_ar', $group)->value('price_role'),
                "«{$group}» must survive OptionPriceRolesSeeder as a line."
            );
        }
    }

    public function test_the_seeder_creates_nothing_on_a_second_run(): void
    {
        $before = DB::table('options')->count();
        $links = DB::table('category_child_option')->count();

        (new ChildTradeVocabulariesSeeder())->run();

        $this->assertSame($before, DB::table('options')->count(), 'A second run creates no option.');
        $this->assertSame($links, DB::table('category_child_option')->count(), 'A second run adds no link.');
    }
}
