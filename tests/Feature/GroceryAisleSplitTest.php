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
            // Seven since 2026-08-24: the owner moved «فسيخ» and «رنجة» out by
            // hand into «أنواع الأسماك والمأكولات البحرية». They were the two
            // rows here that were never counters — a fishmonger WEIGHS فسيخ,
            // and everything else in this list is a place you walk to.
            'الطازج' => ['أقسام الطازج واللحوم', 7, 'لحوم ودواجن'],
            // Four since «فطائر» was reclaimed as a menu band on 2026-08-16.
            'المخبوزات' => ['بنود المخبوزات والحلويات', 4, 'وافل'],
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
     * A producer is not an aisle.
     *
     * «حبوب وغلال - دواجن الخيارات بها هى خيارات السوبر ماركت وليست انواع
     * الحبوب الحقيقة ولا الدواجن من فراخ وسمان وبط وحمام الخ» — owner,
     * 2026-08-16.
     *
     * The split above put every carrier of the old grab-bag onto the counter it
     * answered, and for the shops that was right. For three children it carried
     * the original mistake forward intact: they are not shops at all. «دواجن»
     * answered the fresh counter — أجبان، فسيخ، رنجة — and could not say «بط»;
     * «حبوب وغلال» answered the dry aisle with «مواد غذائية», its whole trade
     * in one shelf label; «خضار وفاكهة» had gone the same way on 2026-08-14.
     *
     * An aisle is where a SHOPPER finds a thing. These three sell the thing, by
     * the tonne, to traders and exporters — so each has a line of its own now,
     * and none of them is asked the aisle. What proves the direction is the
     * modifier each already carried: «حالة الدواجن» (حي · مذبوح · مقطّع) and
     * «وحدة البيع» (بالكيلو · بالأردب · بالطن) only mean anything on top of a
     * line, and there was no line under them.
     *
     * @dataProvider producers
     */
    public function test_a_producer_names_its_trade_and_is_not_asked_an_aisle(
        string $childNameAr,
        string $ownGroup,
        string $mustSay
    ): void {
        /*
         * Under «زراعية وحيوانية», the root the complaint named and the root
         * these three are producers in. Two of them sit under «المحلات أو
         * أونلاين» as well, where the same child is a SHOP — and «خضار وفاكهة»
         * still answers the fresh counter there by the owner's own choice
         * (withdrawn under agriculture on 2026-08-15, kept under the other
         * two). Asserting «no aisle anywhere» would overrule that.
         */
        $root = (int) DB::table('categories')->where('slug', 'agriculture-and-animals')->value('id');

        $offered = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('c.name_ar', $childNameAr)
            ->whereIn('cco.category_id', [0, $root])
            ->whereIn('g.name_ar', self::COUNTERS)
            ->distinct()
            ->pluck('g.name_ar');

        $this->assertSame([], $offered->all(),
            "«{$childNameAr}» is still being asked a supermarket aisle under «زراعية وحيوانية»");

        $words = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('c.name_ar', $childNameAr)
            ->where('g.name_ar', $ownGroup)
            ->pluck('o.name_ar');

        $this->assertContains($mustSay, $words->all(),
            "«{$childNameAr}» cannot say «{$mustSay}» — its own trade is missing");

        // …and the line is a LINE. A trade whose goods are the priced thing and
        // whose group is `descriptive` is a shop window nobody can buy from.
        $this->assertSame('line', DB::table('option_groups')->where('name_ar', $ownGroup)->value('price_role'));
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function producers(): array
    {
        return [
            'دواجن' => ['دواجن', 'أنواع الدواجن والطيور', 'بط'],
            'حبوب وغلال' => ['حبوب وغلال', 'أنواع الحبوب والغلال', 'قمح'],
            // Split in two on 2026-08-24 (ProduceAisleSplitSeeder): the fruit
            // stall and the vegetable stall. «مانجو» now answers under the
            // former, and the trade still names its own goods either way.
            'خضار وفاكهة' => ['خضار وفاكهة', 'الفواكه', 'مانجو'],
            'أعلاف' => ['أعلاف', 'أنواع الأعلاف', 'أعلاف دواجن'],
        ];
    }

    /**
     * A fishmonger sells fish under every root it stands in.
     *
     * «راجع باقي الجذور بنفس الطريقة» — owner, 2026-08-16. The review found one
     * child answering another trade's list outside «زراعية وحيوانية»: «أسماك»
     * #101 held the whole fresh counter under «مصانع» — خضار وفاكهة، سلطة
     * فواكة، ألبان وبيض، أجبان، لحوم ودواجن، مجمدات — while holding only the
     * three fish words under «المحلات», where the owner had narrowed it by hand
     * on 2026-08-12.
     *
     * The gap is structural rather than careless: a withdrawal is keyed by
     * CHILD and a per-root narrowing lives in `prune_links`, so narrowing the
     * shop leaves the factory untouched and nothing reports the difference. A
     * fish plant salts, pickles, freezes and packs fish; it does not make
     * cheese.
     *
     * Per-root differences are legitimate elsewhere — a trade really can answer
     * differently as a factory than as a shop. What is asserted is only that
     * THIS child's answer is fish under all of them.
     */
    public function test_the_fishmonger_sells_fish_under_every_root(): void
    {
        $childId = $this->childId('أسماك');
        $notFish = [];

        foreach (
            DB::table('category_parent_child as pc')
                ->join('categories as r', 'r.id', '=', 'pc.parent_id')
                ->where('pc.child_id', $childId)
                ->get(['r.id', 'r.name_ar']) as $root
        ) {
            $words = DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('cco.child_id', $childId)
                ->whereIn('cco.category_id', [0, $root->id])
                ->whereIn('g.name_ar', self::COUNTERS)
                ->distinct()->pluck('o.name_ar')
                ->reject(fn ($w) => in_array($w, ['فسيخ', 'رنجة', 'أسماك ومأكولات بحرية طازجة'], true));

            if ($words->isNotEmpty()) {
                $notFish[] = "«{$root->name_ar}»: " . $words->implode('، ');
            }
        }

        $this->assertSame([], $notFish,
            '«أسماك» is answering a counter it does not work — ' . implode(' · ', $notFish));
    }

    /**
     * «راجع باقي ابناء زراعية وحيوانية بنفس الطريقة» — owner, 2026-08-16.
     *
     * The review, held as a rule. Every one of the root's nine children sells a
     * GOOD, so every one of them must have a line naming that good — and the
     * two failures it found were both of the same kind: a list that describes
     * where a shopper would look, or a grab-bag restating the child's own name,
     * standing in for the trade.
     *
     * The three grab-bag rows are named because they are the ones that read as
     * a vocabulary while saying nothing. «مستلزمات زراعية» on a feed merchant
     * is true of every business under this root and distinguishes none of them,
     * which is the property that makes it useless as a line: a customer
     * narrowing by it narrows by nothing, and a merchant pricing on it prices
     * everything he sells at one rate.
     */
    public function test_every_agriculture_child_names_what_it_sells(): void
    {
        $root = (int) DB::table('categories')->where('slug', 'agriculture-and-animals')->value('id');

        $empty = ['مستلزمات زراعية', 'ماشية وطيور', 'معدات ومستلزمات'];
        $mute = [];

        foreach (
            DB::table('category_parent_child as pc')
                ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $root)
                ->get(['c.id', 'c.name_ar']) as $child
        ) {
            $lines = DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('cco.child_id', $child->id)
                ->whereIn('cco.category_id', [0, $root])
                ->where('g.price_role', 'line')
                ->pluck('o.name_ar')
                ->reject(fn ($word) => in_array($word, $empty, true));

            if ($lines->count() < 2) {
                $mute[] = "«{$child->name_ar}» #{$child->id} (" . $lines->count() . ')';
            }
        }

        $this->assertSame([], $mute,
            'these cannot name what they sell: ' . implode('، ', $mute));
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

        /*
         * It used to be the two generic menu bands, «مشروبات ساخنة» and
         * «مشروبات باردة». The child has since been given a line group of its
         * own — «أصناف العصائر والمشروبات», عصير مانجو، سوبيا، عصير قصب — and
         * the owner withdrew both bands under all three of its roots (شركات on
         * 2026-08-16, مصانع on 08-20, المحلات on 08-21).
         *
         * Which is the same ruling this test records, one level finer: a juice
         * bar prepares, and what it prepares has names. «مشروبات باردة» as a
         * priced heading means every glass in the shop is one price.
         */
        $this->assertSame([], $bandsOf('عصائر', 'بنود المنيو'), '«عصائر» is priced by the temperature of the glass');

        $this->assertContains('عصير مانجو', $bandsOf('عصائر', 'أصناف العصائر والمشروبات'),
            '«عصائر» lost the list it prepares');
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

    /**
     * «المخابز والحلويات مطابخ» · «البقالة تحتفظ بالمعبأ فقط» — owner,
     * 2026-08-10, closing the last of the shop-versus-kitchen questions.
     *
     * The bakery counter is the one group both kinds of trade share, and the
     * line runs THROUGH it rather than around it: what is only ever made fresh
     * stays with the kitchens, and what is also sold wrapped is on both.
     *
     * That is why this group is «بنود» and its four siblings are «أقسام» — it
     * is a counter somebody works at, and the grocers keep only the part of it
     * that comes in a packet.
     *
     * Four rows, not five: «فطائر» became a menu band on 2026-08-16 and is
     * asserted where it lives now, one test down. The ruling it was made under
     * is untouched — a grocer still does not claim it.
     */
    public function test_only_a_kitchen_bakes_and_a_grocer_stocks_the_wrapped_version(): void
    {
        $carriersOf = fn (string $word) => DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('g.name_ar', 'بنود المخبوزات والحلويات')->where('o.name_ar', $word)
            ->distinct()->pluck('c.name_ar')->sort()->values()->all();

        // Made fresh: the two kitchens and nobody else.
        foreach (['وافل'] as $prepared) {
            $this->assertSame(
                ['حلويات', 'مخابز'],
                $carriersOf($prepared),
                "«{$prepared}» is made on the premises and a grocer is claiming it"
            );
        }

        /*
         * Sold wrapped: the kitchens AND the three markets — unless the owner
         * has said otherwise about one of them. «مخابز» gave up «آيس كريم» on
         * 2026-08-16 03:05, and a bakery that does not keep a freezer is a
         * reading of the trade, not a regression. The withdrawal record is
         * consulted rather than the list being edited, so the next such call
         * needs no change here at all.
         *
         * What stays absolute is the direction the split drew: a grocer stocks
         * the wrapped version. If a MARKET loses one of these, the aisle has
         * been taken apart again.
         */
        $withdrawn = fn (string $child, string $word) => DB::table('category_child_option_decisions as d')
            ->join('options as o', 'o.id', '=', 'd.option_id')
            ->join('category_children_master as c', 'c.id', '=', 'd.child_id')
            ->where('c.name_ar', $child)->where('o.name_ar', $word)
            ->where('d.kind', \App\Services\Catalog\ChildOptionDecisions::WITHDRAWN)
            ->exists();

        foreach (['مخبوزات', 'آيس كريم', 'حلويات وشوكولاتة'] as $packaged) {
            $carriers = $carriersOf($packaged);

            foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $market) {
                $this->assertContains($market, $carriers, "«{$market}» lost «{$packaged}»");
            }

            foreach (['مخابز', 'حلويات'] as $kitchen) {
                if ($withdrawn($kitchen, $packaged)) {
                    continue;
                }

                $this->assertContains($kitchen, $carriers, "«{$kitchen}» lost «{$packaged}»");
            }
        }
    }

    /**
     * …and a general market still sees the counters, because it really does run
     * them — minus whatever it has said it does not.
     *
     * All five were required of all three. «أقسام البقالة الجافة» is no longer
     * one of هايبر ماركت's: on 2026-08-21 the owner withdrew مواد غذائية،
     * بهارات، مكرونات وأرز وحبوب، زيوت وسمن and سناكس وتسالي from it and moved
     * the shop onto the twenty ranges, and «معلبات» was the single row that got
     * left behind (GroceryAisleSplitSeeder finishes it).
     *
     * Dry goods are STOCKED. The four counters he kept — fresh, meat, drinks,
     * home — are things a shop does work at, and that is the line the split was
     * looking for and did not quite find. So the assertion asks for the
     * counters he has not spoken against, and still fails if one goes missing
     * on its own.
     */
    public function test_a_supermarket_still_sees_every_counter(): void
    {
        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $name) {
            $childId = $this->childId($name);

            $given_up = DB::table('category_child_option_decisions as d')
                ->join('options as o', 'o.id', '=', 'd.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('d.child_id', $childId)->where('d.kind', 'withdrawn')
                ->whereIn('g.name_ar', self::COUNTERS)
                ->distinct()->pluck('g.name_ar')->all();

            $due = array_values(array_diff(self::COUNTERS, $given_up));
            $offered = array_intersect($this->groupsOffered($name), self::COUNTERS);

            $this->assertNotEmpty($due, "«{$name}» was left with no counter at all");

            $this->assertSame(
                $due,
                array_values(array_intersect($due, $offered)),
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

        // The SHOP. «مزارع سمكية» was on this line until 2026-08-16 02:06, when
        // the owner withdrew the aisle word from it — and he is right: a fish
        // farm is not a fish counter. It says what it sells through «أنواع
        // الثروة الحيوانية والسمكية» — بلطي، بوري، قراميط، زريعة — which names
        // the species where the aisle only said «fish». Asserted below, because
        // the claim this test owns is «a fishmonger can say it sells fish», not
        // «by way of this particular word».
        $this->assertContains(
            'أسماك ومأكولات بحرية طازجة',
            $this->optionNamesOf('أسماك'),
            '«أسماك» cannot say it sells fish'
        );

        $farm = $this->optionNamesOf('مزارع سمكية');

        $this->assertNotEmpty(
            array_intersect(['بلطي', 'بوري', 'قراميط', 'زريعة وإصبعيات'], $farm),
            '«مزارع سمكية» can name neither an aisle nor a species — it went mute'
        );

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
        /*
         * ⚠ 2026-08-24: «أصناف المنتجات الغذائية» is retired. «إذا كان هناك بند
         * مثل زيوت وسمن اعمل مجموعة لها وأضف فروعها … وبعد اكتمال كل فروعها
         * نلغيها» — the owner, and he is right: the twenty rows named SHELVES,
         * and a shelf cannot be priced. Thirteen of them became lists of their
         * own and «مواد غذائية» took every one.
         *
         * The claim this test makes is unchanged and is the one that matters:
         * a wholesaler with no market list is not left mute.
         */
        $offered = $this->groupsOffered('مواد غذائية');

        $this->assertNotContains(
            'أصناف المنتجات الغذائية',
            $offered,
            'a retired group still reaches a child'
        );

        foreach (['أنواع الزيوت والسمن', 'أنواع البهارات والتوابل', 'أنواع المعلبات'] as $replacement) {
            $this->assertContains(
                $replacement,
                $offered,
                '«مواد غذائية» lost the only list it had'
            );
        }

        /*
         * «استيراد وتصدير» was the second name here and is no longer a trader
         * in ranges at all. On 2026-08-20 the owner emptied the food list off
         * it, and the fulfilment axes, and جملة/تجزئة, and new/used, and
         * returns — leaving «خدمات التخليص الجمركي» standing.
         *
         * A customs broker files papers on somebody else's cargo. He never owns
         * a range, so «which ranges do you deal in» is the one question this
         * group exists to ask and the one he cannot answer. The claim the test
         * makes about him is the claim that still holds: he is not left mute.
         */
        $this->assertNotEmpty(
            $this->groupsOffered('استيراد وتصدير'),
            '«استيراد وتصدير» lost the only list it had'
        );

        /*
         * سوبر ماركت، مني ماركت and هايبر ماركت stood here too, on the reading
         * that a child with a priced aisle list has no use for the ranges. On
         * 2026-08-21 the owner decided the opposite for all three: he pinned
         * the twenty ranges onto them and withdrew from the aisles the rows the
         * ranges repeat, keeping only the counters — خضار وفاكهة، فسيخ، رنجة،
         * مجمدات، ألبان وبيض.
         *
         * That is a better line than this one drew. A counter is work the shop
         * does and belongs in a priced heading; a range is stock it carries and
         * belongs in a modifier. The redundancy this test was written to stop
         * is a child holding the same WORD twice, and the test below is the one
         * that measures it — on all eight children, and on the word.
         */
        $this->assertNotContains(
            'أصناف المنتجات الغذائية',
            $this->groupsOffered('مجمدات'),
            '«مجمدات» is still asked the same question twice'
        );
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
