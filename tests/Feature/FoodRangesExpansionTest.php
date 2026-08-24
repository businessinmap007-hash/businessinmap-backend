<?php

namespace Tests\Feature;

use App\Services\MerchantOfferingVocabulary;
use Database\Seeders\FoodRangesExpansionSeeder;
use Database\Seeders\OptionPriceRolesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «قم بمراجعة فروع أصناف المنتجات الغذائية … إذا كان هناك بند مثل زيوت وسمن
 *  اعمل مجموعة لها وأضف فروعها، وبعد اكتمال كل فروعها نلغيها ونضيف المجموعات
 *  إلى السوبر ماركت والهايبر والميني ماركت» — المالك، 2026-08-24.
 *
 * Twenty rows named SHELVES and none of them was ever priced, because a shelf
 * is not a thing. Thirteen became lists of their own, seven pointed at lists
 * that already existed, and the parent was switched off.
 *
 * Rolls back.
 */
class FoodRangesExpansionTest extends TestCase
{
    use DatabaseTransactions;

    private const RETIRED = 'أصناف المنتجات الغذائية';

    /** @return array<string,mixed> */
    private function data(): array
    {
        return require base_path('database/seeders/data/food_ranges_expansion.php');
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
    private function groupsOf(string $childName)
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('c.name_ar', $childName)
            ->where('g.is_active', 1)
            ->distinct()
            ->pluck('g.name_ar');
    }

    // ── every shelf now has a list behind it ────────────────────────────────

    /**
     * The whole deliverable, said once: each of the twenty rows the owner asked
     * about is answered by a group that exists and is not empty.
     *
     * @dataProvider shelves
     */
    public function test_every_shelf_became_a_list(string $shelf, string $group, string $sample): void
    {
        $this->assertContains(
            $shelf,
            $this->optionsOf(self::RETIRED)->all(),
            "«{$shelf}» is not one of the twenty — this map has drifted."
        );

        $words = $this->optionsOf($group);

        $this->assertNotEmpty($words, "«{$shelf}» still has nothing behind it.");
        $this->assertContains($sample, $words->all(), "«{$group}» cannot say «{$sample}»");
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function shelves(): array
    {
        // Seven of the twenty point at lists written for the trades that sell
        // them — the meat counter, the fishmonger, the bakery, the farm. The
        // other thirteen were written for this.
        return [
            'حبوب وبقوليات' => ['حبوب وبقوليات', 'أنواع الحبوب والغلال', 'عدس'],
            'أرز' => ['أرز', 'أنواع الحبوب والغلال', 'أرز بسمتي'],
            'دقيق' => ['دقيق', 'أنواع الحبوب والغلال', 'دقيق فاخر'],
            'ألبان وأجبان' => ['ألبان وأجبان', 'أنواع الألبان والأجبان', 'جبنة رومي'],
            'لحوم ودواجن مجمدة' => ['لحوم ودواجن مجمدة', 'أنواع اللحوم', 'كندوز'],
            'أسماك ومأكولات بحرية' => ['أسماك ومأكولات بحرية', 'أنواع الأسماك والمأكولات البحرية', 'جمبري'],
            'مخبوزات معبأة' => ['مخبوزات معبأة', 'أنواع المخبوزات', 'توست'],

            'مكرونة' => ['مكرونة', 'أنواع المكرونة', 'شعرية'],
            'زيوت وسمن' => ['زيوت وسمن', 'أنواع الزيوت والسمن', 'زيت ذرة'],
            'سكر ومحليات' => ['سكر ومحليات', 'أنواع السكر والمحليات', 'سكر أبيض'],
            'بهارات وتوابل' => ['بهارات وتوابل', 'أنواع البهارات والتوابل', 'كمون'],
            'معلبات' => ['معلبات', 'أنواع المعلبات', 'تونة معلبة'],
            'خل ومخللات' => ['خل ومخللات', 'أنواع المخللات والخل', 'مخلل لفت'],
            'صلصات وشوربات' => ['صلصات وشوربات', 'أنواع الصلصات والشوربات', 'كاتشب'],
            'عسل ومربى' => ['عسل ومربى', 'أنواع العسل والمربى', 'عسل نحل'],
            'شاي وقهوة' => ['شاي وقهوة', 'أنواع الشاي والقهوة', 'بن محوج'],
            'مكسرات وتسالي' => ['مكسرات وتسالي', 'أنواع المكسرات والتسالي', 'لب سوري'],
            'حلويات وشوكولاتة معبأة' => ['حلويات وشوكولاتة معبأة', 'أنواع الحلويات المعبأة', 'ويفر'],
            'أغذية أطفال' => ['أغذية أطفال', 'أنواع أغذية الأطفال', 'لبن أطفال'],
            'عصائر ومشروبات' => ['عصائر ومشروبات', 'أنواع المشروبات المعبأة', 'مياه معدنية'],
        ];
    }

    public function test_the_thirteen_are_priceable_and_stay_priceable(): void
    {
        // The recorded landmine: a group missing from data/option_price_roles.php
        // is pushed back to `descriptive` on the next run and silently leaves
        // the pricing screen.
        (new OptionPriceRolesSeeder())->run();

        foreach (array_keys($this->data()['groups']) as $group) {
            $this->assertSame(
                'line',
                DB::table('option_groups')->where('name_ar', $group)->value('price_role'),
                "«{$group}» must survive OptionPriceRolesSeeder as a line."
            );
        }
    }

    // ── the shelf list itself ───────────────────────────────────────────────

    public function test_the_replaced_group_is_stopped_and_reaches_nobody(): void
    {
        $group = DB::table('option_groups')->where('name_ar', self::RETIRED)->first();

        $this->assertNotNull($group, 'Nothing in this taxonomy is deleted.');
        $this->assertSame(0, (int) $group->is_active);

        $optionIds = DB::table('options')->where('group_id', $group->id)->pluck('id');

        $this->assertCount(20, $optionIds, 'The twenty rows stay inside it as the record.');

        /*
         * A retired row that still reaches a child is worse than one nobody
         * retired: the reconciliation backstop reads `category_child_option`,
         * so it would keep restoring a word no screen shows. Same rule
         * ChildOptionDecisionTest states for the whole taxonomy.
         */
        $this->assertSame(0, DB::table('category_child_option')->whereIn('option_id', $optionIds)->count());
        $this->assertSame(0, DB::table('category_child_option_decisions')->whereIn('option_id', $optionIds)->count());
    }

    public function test_no_seeder_still_declares_the_retired_group(): void
    {
        // «مواد غذائية ومنظفات» #110 was granted the whole twenty by
        // company_child_vocabularies.php and got them back on every run. A file
        // that keeps declaring a row it gave away is this taxonomy's oldest bug.
        $files = glob(base_path('database/seeders/data/*.php'));

        foreach ($files as $file) {
            if (str_contains($file, 'option_price_roles') || str_contains($file, 'food_ranges_expansion')) {
                continue;   // both name it on purpose — see their own comments
            }

            $body = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                "'" . self::RETIRED . "' => 'all'",
                $body,
                basename($file) . ' still grants a retired group'
            );
        }
    }

    // ── who carries the thirteen ────────────────────────────────────────────

    /**
     * @dataProvider carriers
     */
    public function test_the_lists_reached_the_shops_that_asked_for_them(string $child): void
    {
        $groups = $this->groupsOf($child);

        foreach (array_keys($this->data()['groups']) as $group) {
            $this->assertContains($group, $groups->all(), "«{$child}» cannot say «{$group}»");
        }
    }

    /** @return array<string,array{0:string}> */
    public static function carriers(): array
    {
        return [
            // «ونضيف المجموعات إلى السوبر ماركت والهايبر والميني ماركت»
            'سوبر ماركت' => ['سوبر ماركت'],
            'هايبر ماركت' => ['هايبر ماركت'],
            'مني ماركت' => ['مني ماركت'],
            // …and the two dry grocers, who carried the retired group too and
            // would otherwise have been left holding nothing.
            'مواد غذائية' => ['مواد غذائية'],
            'مواد غذائية ومنظفات' => ['مواد غذائية ومنظفات'],
        ];
    }

    public function test_a_dry_grocer_is_not_handed_a_fridge(): void
    {
        // Every one of the thirteen is shelf-stable, which is what a «مواد
        // غذائية» shop is. The fresh lists stay with the markets — that is the
        // whole difference between a mini-market and a dry grocer.
        $groups = $this->groupsOf('مواد غذائية');

        foreach (['أنواع اللحوم', 'أنواع الأسماك والمأكولات البحرية', 'الفواكه', 'الخضروات'] as $fresh) {
            $this->assertNotContains($fresh, $groups->all(), "«مواد غذائية» was handed «{$fresh}»");
        }
    }

    // ── one row per variety ─────────────────────────────────────────────────

    /**
     * «البرتقال يكون كل نوع فى اختيار … لأن المانجو أنواع كتيرة فالأفضل يكون كل
     *  نوع منفرد يُسعّر ويكون له كمية».
     *
     * A variety cannot be a modifier here, and this is the reason:
     * `available_quantity` lives on `menu_items`, which is the LINE. A modifier
     * can never run out on its own, so «عويس نفد والزبدية موجودة» is only
     * sayable if each variety is its own row.
     */
    public function test_each_variety_is_its_own_priceable_row(): void
    {
        $fruit = $this->optionsOf('الفواكه');

        foreach (['برتقال سكري', 'برتقال أبو سرة', 'برتقال بلدي', 'مانجو عويس', 'مانجو زبدية', 'مانجو فص'] as $variety) {
            $this->assertContains($variety, $fruit->all());
        }

        // The generic stays: a stall that sells «برتقال» full stop is a stall.
        foreach (['برتقال', 'مانجو'] as $generic) {
            $this->assertContains($generic, $fruit->all());
        }

        $this->assertSame(
            'line',
            DB::table('option_groups')->where('name_ar', 'الفواكه')->value('price_role')
        );
    }

    public function test_the_missing_vegetables_arrived(): void
    {
        $veg = $this->optionsOf('الخضروات');

        foreach (['بقدونس', 'كزبرة خضراء', 'شبت', 'نعناع', 'مشروم', 'كرفس', 'فجل', 'ورق عنب'] as $word) {
            $this->assertContains($word, $veg->all(), "«الخضروات» cannot say «{$word}»");
        }
    }

    public function test_the_row_that_named_two_vegetables_was_split(): void
    {
        // «لفت وفجل» is two roots at two prices in one row: pickable, never
        // priceable. Renamed to «لفت», with «فجل» written beside it.
        $this->assertNotContains('لفت وفجل', $this->optionsOf('الخضروات')->all());

        foreach (['لفت', 'فجل'] as $word) {
            $rows = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', 'الخضروات')->where('o.name_ar', $word)->count();

            $this->assertSame(1, $rows, "«{$word}» exists {$rows} times in الخضروات");
        }
    }

    /**
     * The rename has to reach the file that DECLARED the row, or the next run
     * of ChildTradeVocabulariesSeeder writes «لفت وفجل» back beside it. Same
     * lesson as the three «Tilapia (Agri)», learned on the same table.
     */
    public function test_no_file_still_declares_the_old_name(): void
    {
        foreach (glob(base_path('database/seeders/data/*.php')) as $file) {
            if (str_contains($file, 'food_ranges_expansion')) {
                continue;   // it names the old name to rename it
            }

            $this->assertStringNotContainsString(
                "'لفت وفجل'",
                (string) file_get_contents($file),
                basename($file) . ' still declares a row that was renamed'
            );
        }
    }

    // ── what the merchant is actually shown ─────────────────────────────────

    public function test_a_market_is_offered_the_varieties_under_their_own_section(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');

        $business = DB::table('users')->where('type', 'business')
            ->where('category_child_id', $childId)->orderBy('id')->first();

        if (! $business) {
            $this->markTestSkipped('No supermarket business in this database.');
        }

        $vocab = app(MerchantOfferingVocabulary::class)->for((int) $business->id, $childId);

        $sections = $vocab['lines']->pluck('group_name')->unique();

        $this->assertNotContains(self::RETIRED, $sections->all(), 'a retired list is still offered');

        // At least one of the new sections reaches him — which of them depends
        // on his own ticks, and that narrowing is the point of the screen.
        $this->assertNotEmpty($vocab['lines'], 'a supermarket with nothing to price');
    }

    // ── idempotency ─────────────────────────────────────────────────────────

    public function test_a_second_run_changes_nothing(): void
    {
        $options = DB::table('options')->count();
        $links = DB::table('category_child_option')->count();
        $groups = DB::table('option_groups')->count();

        (new FoodRangesExpansionSeeder())->run();

        $this->assertSame($options, DB::table('options')->count(), 'A second run creates no option.');
        $this->assertSame($links, DB::table('category_child_option')->count(), 'A second run adds no link.');
        $this->assertSame($groups, DB::table('option_groups')->count(), 'A second run creates no group.');
    }
}
