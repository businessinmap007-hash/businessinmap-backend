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
    /**
     * All five, and every one of them is now switched off.
     *
     * Kept as a list because half this file's tests are of the shape «this
     * child is NOT asked an aisle» — the complaint the owner made about
     * «حبوب وغلال» and «دواجن» in August. Those claims did not weaken when the
     * groups were retired; they became absolute, and they still have to be
     * checked, because a seeder that re-grants a retired row is the one failure
     * mode a switch-off has.
     */
    private const AISLES = [
        'أقسام الطازج واللحوم',
        'بنود المخبوزات والحلويات',
        'أقسام البقالة الجافة',
        'أقسام المشروبات',
        'أقسام المنزل والعناية',
    ];

    /**
     * ⚠ «نظّف البقالة الجافة والمشروبات» — المالك، 2026-08-24.
     *
     * Two of the five are retired. Every word in both had become a list of its
     * own — «زيوت وسمن» → «أنواع الزيوت والسمن», «مشروبات» → «أنواع المشروبات
     * المعبأة» — and a shelf nobody can price is what this whole day was about.
     *
     * The split itself is untouched by it: it still moved twenty-seven rows out
     * of one grab-bag into the five counters its own link data drew. Two of
     * those counters were later replaced by finer lists, which is what a
     * taxonomy doing its job looks like.
     */
    private const RETIRED = self::AISLES;


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
     * ⚠ The size-and-role check that stood here is gone with its subject.
     *
     * It asserted that each of the five drawers is a live `line` group of a
     * given size. All five were switched off between 2026-08-24 16:00 and the
     * end of that day — «نظّف البقالة الجافة والمشروبات», then «نظّف أقسام
     * المنزل والعناية», then «نظّف أقسام الطازج واللحوم وبنود المخبوزات» — so
     * every one of them fails `is_active = 1` by design.
     *
     * What the split PROMISED is asserted below and is untouched by it: nothing
     * was created or lost, no child is asked one word twice, each specialist
     * sees only its own trade, and the parent is left standing and empty.
     */

    /**
     * The two the owner cleaned out, and the promise that holds for both: they
     * are stopped, not deleted, they reach nobody, and every word they held is
     * sayable somewhere a merchant can put a price on it.
     */
    public function test_the_two_replaced_counters_are_stopped_and_reach_nobody(): void
    {
        foreach (self::RETIRED as $name) {
            $group = DB::table('option_groups')->where('name_ar', $name)->first();

            $this->assertNotNull($group, 'Nothing in this taxonomy is deleted.');
            $this->assertSame(0, (int) $group->is_active, "«{$name}» is still live");

            $ids = DB::table('options')->where('group_id', $group->id)->pluck('id');

            $this->assertNotEmpty($ids, 'the rows stay inside it as the record');
            $this->assertSame(0, DB::table('category_child_option')->whereIn('option_id', $ids)->count());
            $this->assertSame(0, DB::table('category_child_option_decisions')->whereIn('option_id', $ids)->count());
        }

        /*
         * And the two trades that would have been left mute by it. Each had
         * exactly ONE priced list on the whole platform — «بن» had «أقسام
         * البقالة الجافة» (2 rows), «منظفات» had «أقسام المنزل والعناية» (6) —
         * so both were granted their replacements in the same transaction,
         * before the switch-off.
         */
        $linesOf = fn (string $child) => DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($child))
            ->where('g.is_active', 1)->where('g.price_role', 'line')
            ->distinct()->pluck('g.name_ar')->all();

        foreach ([
            'بن' => ['أنواع الشاي والقهوة', 'أنواع المكسرات والتسالي'],
            'منظفات' => [
                'أنواع المنظفات', 'أصناف العناية الشخصية', 'مستلزمات الأطفال',
                'مستلزمات الحيوانات الأليفة', 'أنواع الفحم والوقود المنزلي',
                'مستلزمات المنزل',
            ],
        ] as $child => $expected) {
            foreach ($expected as $group) {
                $this->assertContains($group, $linesOf($child), "«{$child}» cannot say «{$group}»");
            }
        }
    }

    /**
     * The point of the whole slice, one step further on.
     *
     * The split gave each specialist its own COUNTER and took the other four
     * away — before it, every one of these saw all 27 words. The counters have
     * since been replaced by the varieties behind them, so the claim is now
     * made against the list each trade actually prices: a fishmonger is asked
     * about fish, and about nothing else on that shelf.
     *
     * @dataProvider specialists
     */
    public function test_a_specialist_is_asked_its_own_trade_and_no_aisle(string $childNameAr, string $expected): void
    {
        $offered = $this->groupsOffered($childNameAr);

        $this->assertContains($expected, $offered, "«{$childNameAr}» cannot say «{$expected}»");

        $this->assertSame(
            [],
            array_values(array_intersect($offered, self::AISLES)),
            "«{$childNameAr}» is still being offered a retired aisle"
        );
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function specialists(): array
    {
        return [
            'سمّاك' => ['أسماك', 'أنواع الأسماك والمأكولات البحرية'],
            'مخبز' => ['مخابز', 'أنواع المخبوزات'],
            'محل منظفات' => ['منظفات', 'أنواع المنظفات'],
            'مجمدات' => ['مجمدات', 'أنواع المجمدات'],
            'محل بن' => ['بن', 'أنواع الشاي والقهوة'],
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
            ->whereIn('g.name_ar', self::AISLES)
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
                ->whereIn('g.name_ar', self::AISLES)
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

        /*
         * …and it can name what it sells. In August that was ONE aisle row,
         * «بن وشاي», minted because no list existed. On 2026-08-24 the aisle
         * was retired and he got the list itself — nine rows he can price, and
         * the nuts beside them, because a محمصة roasts the لب on the same fire.
         *
         * Granted BEFORE the retirement, which is this file's own rule stated
         * for the fishmongers: grant first, then revoke, so a shop is never
         * without a way to say what it sells — not even for the width of one
         * transaction.
         */
        $this->assertContains('بن محوج', $bandsOf('بن', 'أنواع الشاي والقهوة'));
        $this->assertContains('لب سوري', $bandsOf('بن', 'أنواع المكسرات والتسالي'));
        $this->assertSame([], $bandsOf('بن', 'أقسام البقالة الجافة'), 'a retired row still reaches «بن»');

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

            /*
             * «أقسام المشروبات» stood here — two rows, «عصائر» and «مشروبات» —
             * and the claim was that the drinks moved to the aisle rather than
             * disappearing. On 2026-08-24 they moved once more, to «أنواع
             * المشروبات المعبأة»: مياه معدنية، مشروبات غازية، عصائر معبأة،
             * مشروبات طاقة. Nine rows a market can put a price on, where the
             * aisle had two it could not.
             *
             * The claim is the same claim. Only the drawer changed.
             */
            $this->assertContains(
                'أنواع المشروبات المعبأة',
                $this->groupsOffered($name),
                "«{$name}» lost drinks altogether instead of moving them to the list"
            );
        }
    }

    /**
     * «المخابز والحلويات مطابخ» · «البقالة تحتفظ بالمعبأ فقط» — owner,
     * 2026-08-10, and the line he drew outlived the drawer it was drawn in.
     *
     * «بنود المخبوزات والحلويات» was retired on 2026-08-24. Two of its four
     * rows are things a kitchen MAKES — «وافل» and «آيس كريم» — so they were
     * MOVED into «أصناف الحلويات والجاتوه» rather than replaced, and a regroup
     * carries `category_child_option` with it. The other two were shelf names:
     * «مخبوزات» → «أنواع المخبوزات» (12 loaves), «حلويات وشوكولاتة» → the
     * patisserie list for the kitchens and «أنواع الحلويات المعبأة» for the
     * grocers, which is the packaged/fresh line said properly for the first
     * time.
     */
    public function test_only_a_kitchen_bakes_and_a_grocer_stocks_the_wrapped_version(): void
    {
        $carriersOf = fn (string $group, string $word) => DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('g.name_ar', $group)->where('o.name_ar', $word)
            ->distinct()->pluck('c.name_ar')->sort()->values()->all();

        // Made fresh: «وافل» kept both kitchens through the move and reached no
        // grocer on the way. A regroup that changed a carrier set is a regroup
        // that lost links.
        $this->assertSame(
            ['حلويات', 'مخابز'],
            $carriersOf('أصناف الحلويات والجاتوه', 'وافل'),
            '«وافل» is made on the premises and a grocer is claiming it'
        );

        /*
         * «آيس كريم» is the withdrawal that proves the rule. «مخابز» gave it up
         * on 2026-08-16 03:05 — a bakery that keeps no freezer — and the ledger
         * held that line through the move AND through the grant of the whole
         * patisserie list to that same child hours later. One link refused out
         * of twenty, and it was the right one.
         */
        $icecream = $carriersOf('أصناف الحلويات والجاتوه', 'آيس كريم');

        $this->assertNotContains('مخابز', $icecream, 'a withdrawal was undone by a regroup');
        $this->assertContains('حلويات', $icecream);

        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $market) {
            $this->assertContains($market, $icecream, "«{$market}» lost «آيس كريم»");
        }

        // Sold wrapped: the grocers keep the packet, and now by its own name.
        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $market) {
            $this->assertContains(
                $market,
                $carriersOf('أنواع المخبوزات', 'توست'),
                "«{$market}» lost the wrapped loaf"
            );

            $this->assertContains(
                $market,
                $carriersOf('أنواع الحلويات المعبأة', 'شوكولاتة ألواح'),
                "«{$market}» lost the wrapped bar"
            );
        }
    }

    /**
     * …and a general market still sees everything it runs — minus whatever it
     * has said it does not.
     *
     * All five aisle drawers were required of all three markets when this test
     * was written. All five are retired now, so the claim moves onto the lists
     * that replaced them: the shape being guarded was never «these five group
     * names», it was «a market was not quietly emptied while the words were
     * being rearranged under it».
     *
     * The withdrawal ledger is still consulted rather than the list edited —
     * «سوبر ماركت» and «مني ماركت» gave up «مجمدات» by hand on 2026-08-24, so
     * neither is handed a freezer list here, and that is a reading of the two
     * shops rather than a regression.
     */
    public function test_a_market_still_sees_everything_it_runs(): void
    {
        $due = [
            'الفواكه', 'الخضروات', 'أنواع اللحوم', 'أنواع الدواجن والطيور',
            'أنواع الأسماك والمأكولات البحرية', 'أنواع الألبان والأجبان',
            'أنواع المخبوزات', 'أنواع الحبوب والغلال', 'أنواع المعلبات',
            'أنواع الزيوت والسمن', 'أنواع البهارات والتوابل',
            'أنواع المشروبات المعبأة', 'أنواع الحلويات المعبأة',
        ];

        foreach (['سوبر ماركت', 'مني ماركت', 'هايبر ماركت'] as $name) {
            $offered = $this->groupsOffered($name);

            foreach ($due as $group) {
                $this->assertContains($group, $offered, "«{$name}» lost «{$group}»");
            }

            $this->assertSame(
                [],
                array_values(array_intersect($offered, self::AISLES)),
                "«{$name}» is still being offered a retired aisle"
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
        /*
         * The aisle word this test was written about — «أسماك ومأكولات بحرية
         * طازجة» — is inside a retired group since 2026-08-24 and reaches
         * nobody. It was a coarser answer than the one the fishmonger has now:
         * «أنواع الأسماك والمأكولات البحرية» names twenty-one species where the
         * aisle only said «fish».
         *
         * The claim this test owns is «a fishmonger can say it sells fish», not
         * «by way of this particular word», so it is asked of the list he
         * actually prices.
         */
        $this->assertNotEmpty(
            array_intersect(['جمبري', 'دنيس', 'أسماك بلطي'], $this->optionNamesOf('أسماك')),
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
        $groups = array_merge(self::AISLES, [
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
