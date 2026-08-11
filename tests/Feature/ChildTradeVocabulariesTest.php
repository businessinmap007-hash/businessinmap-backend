<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every trade must be able to say what it sells.
 *
 * Six of «مكاتب»'s thirteen children could not, and ALL THREE of
 * «تكنولوجيا»'s: they carried «نمط تقديم الخدمة», a payment group, and no
 * `line` vocabulary at all. A customer searching for a water-tank cleaner, a
 * cash-in-transit guard or a fire-alarm installer found businesses that could
 * describe how they work and never what they do.
 *
 * @see \Database\Seeders\ChildTradeVocabulariesSeeder
 */
class ChildTradeVocabulariesTest extends TestCase
{
    use DatabaseTransactions;

    /** Roots finished by the OFFICES rule — every child owns a `line`. */
    private const ROOTS = ['offices', 'technology'];

    /** Every data file the seeder reads, in its order. */
    private const FILES = [
        'office_child_vocabularies.php',
        'technology_child_vocabularies.php',
        'factory_child_vocabularies.php',
        'company_child_vocabularies.php',
        'exhibition_child_vocabularies.php',
        'crafts_child_vocabularies.php',
        'shop_child_vocabularies.php',
        'entertainment_child_vocabularies.php',
        'stray_child_vocabularies.php',
        'health_child_vocabularies.php',
        'hall_child_vocabularies.php',
        'workshop_child_vocabularies.php',
        'shipping_child_vocabularies.php',
        'agriculture_child_vocabularies.php',
    ];

    /**
     * A child whose emptiness is a decision, not a gap.
     *
     * «مأذون شرعى» was here until 2026-08-11. What the owner had withdrawn from
     * it on 08-10 were its six GENERIC options — payment and service-mode — and
     * he then asked for the trade itself: «هل هناك مهام للمأذون الشرعى تضاف الى
     * مكتبه». The six stay off; the register entries went on.
     *
     * @var array<int,string>
     */
    private const DELIBERATELY_BARE = [];

    /** @return array<int,string> */
    private function lines(int $childId): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.price_role', 'line')
            ->distinct()->pluck('o.name_ar')->all();
    }

    private function childId(string $name): int
    {
        $id = DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if (! $id) {
            $this->markTestSkipped("The «{$name}» child is absent.");
        }

        return (int) $id;
    }

    /** The rule, over every root this seeder speaks for. */
    public function test_every_trade_can_name_what_it_sells(): void
    {
        $mute = [];

        foreach (self::ROOTS as $slug) {
            $rootId = (int) DB::table('categories')->where('slug', $slug)->value('id');

            foreach (
                DB::table('category_parent_child as pc')
                    ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', $rootId)->get(['c.id', 'c.name_ar']) as $child
            ) {
                if (in_array($child->name_ar, self::DELIBERATELY_BARE, true)) {
                    continue;
                }

                if ($this->lines((int) $child->id) === []) {
                    $mute[] = "{$child->name_ar}#{$child->id}@{$slug}";
                }
            }
        }

        $this->assertSame([], $mute, 'these can say how they work and never what they do: ' . implode('، ', $mute));
    }

    /**
     * «مكاتب» is finished: every child answers all three questions.
     *
     * What it sells (`line`), what changes the price (`modifier`) and what
     * describes it (`descriptive`). Twelve of the thirteen had no descriptive
     * axis at all once the owner started removing the payment group, and the
     * platform's shared descriptives are all goods-shaped — «الاستبدال
     * والإرجاع», «التسليم والاستلام», «حالة المنتج» say nothing about an
     * accountant.
     */
    public function test_the_offices_root_answers_all_three_questions(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', 'offices')->value('id');

        $incomplete = [];

        foreach (
            DB::table('category_parent_child as pc')
                ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $rootId)->get(['c.id', 'c.name_ar']) as $child
        ) {
            $roles = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', (int) $child->id)
                ->distinct()->pluck('g.price_role')->all();

            $missing = array_diff(['line', 'modifier', 'descriptive'], $roles);

            if ($missing !== []) {
                $incomplete[] = "{$child->name_ar}#{$child->id} (" . implode('+', $missing) . ')';
            }
        }

        $this->assertSame([], $incomplete, 'these answer only part of themselves: ' . implode('، ', $incomplete));
    }

    /**
     * The engagement basis is an axis only where there are two answers.
     *
     * A مأذون is paid per act and a printing house per job. A modifier with one
     * possible value asks a question that has no second answer — noise on the
     * pricing screen, not an axis — so neither carries «نظام التعاقد».
     */
    public function test_the_engagement_basis_reaches_only_the_trades_with_two_answers(): void
    {
        $carriers = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نظام التعاقد')
            ->distinct()->pluck('co.child_id')->map(fn ($id) => (int) $id)->all();

        foreach (['محاسبة', 'محاماه', 'أمن', 'إدارة صفحات'] as $name) {
            $this->assertContains($this->childId($name), $carriers, "«{$name}» is engaged two ways");
        }

        foreach (['مأذون شرعى', 'طباعة'] as $name) {
            $this->assertNotContains($this->childId($name), $carriers, "«{$name}» is paid one way only");
        }

        // A lawyer is not on a subscription. The group was renamed the day it
        // outgrew the desks it was born for.
        $this->assertFalse(
            DB::table('option_groups')->where('name_ar', 'نظام الاشتراك')->exists(),
            'the old name is back, so there are two groups asking one question'
        );
    }

    /**
     * «مصانع» is a GOODS root and its completion rule is not the offices one.
     *
     * A service IS the priced row, so an office is finished when it has a
     * `line`. A factory sells a catalog product — 986 of them live in
     * `catalog_products` — so a `line` group there would compete with the
     * catalog for the same job. What a factory owes is the ability to say WHAT
     * IT DEALS IN, on whatever axis: the «ماركات السيارات» pattern.
     *
     * Measured against the five axes every goods child already carries, which
     * say nothing about the trade, 26 of the 44 could name nothing at all.
     */
    public function test_every_factory_can_name_what_it_makes(): void
    {
        // Universal to every goods child, plus the two the factory root asks.
        $universal = [
            'حالة المنتج', 'الدفع والسداد', 'التسليم والاستلام',
            'الاستبدال والإرجاع', 'نطاق التعامل', 'نمط تقديم الخدمة',
            'نظام التصنيع', 'الحد الأدنى للطلب',
        ];

        $rootId = (int) DB::table('categories')->where('slug', 'factories')->value('id');

        $mute = [];

        foreach (
            DB::table('category_parent_child as pc')
                ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $rootId)->get(['c.id', 'c.name_ar']) as $child
        ) {
            $groups = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', (int) $child->id)
                ->distinct()->pluck('g.name_ar')->all();

            if (array_diff($groups, $universal) === []) {
                $mute[] = "{$child->name_ar}#{$child->id}";
            }
        }

        $this->assertSame([], $mute, 'these factories cannot name one thing they make: ' . implode('، ', $mute));
    }

    /** Nobody buys the phrase «طوب أحمر». They buy a catalog product. */
    public function test_a_factory_vocabulary_qualifies_a_price_and_is_not_one(): void
    {
        $declared = require database_path('seeders/data/option_price_roles.php');

        foreach ((require database_path('seeders/data/factory_child_vocabularies.php'))['groups'] as $group => $spec) {
            $this->assertNotContains(
                $group,
                $declared['line'],
                "«{$group}» is declared a line — a factory's priced rows are its catalog products"
            );

            $this->assertSame(
                $spec['price_role'],
                DB::table('option_groups')->where('name_ar', $group)->value('price_role'),
                "«{$group}» does not hold the role its file declares"
            );
        }
    }

    /**
     * A furniture SHOP is not asked what a furniture FACTORY is asked.
     *
     * «آثاث» #116 stands under مصانع، معارض، شركات. Production basis and
     * minimum order belong to the maker, so they are written with
     * `category_id = 23` — the first use of the per-root column for anything
     * other than a hand edit. CategoryChildOptionScope's own docblock uses this
     * exact example.
     */
    public function test_only_the_factory_is_asked_how_it_produces(): void
    {
        $scope = new \App\Services\CategoryChildOptionScope;

        $childId = $this->childId('آثاث');

        $asks = function (int $rootId) use ($scope, $childId): bool {
            return DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('o.id', $scope->idsFor($childId, $rootId))
                ->whereIn('g.name_ar', ['نظام التصنيع', 'الحد الأدنى للطلب'])
                ->exists();
        };

        $factories = (int) DB::table('categories')->where('slug', 'factories')->value('id');
        $showrooms = (int) DB::table('categories')->where('slug', 'exhibitions')->value('id');

        $this->assertTrue($asks($factories), 'a furniture factory is not asked how it produces');
        $this->assertFalse($asks($showrooms), 'a furniture SHOWROOM is being asked its minimum order quantity');

        // And no shared row exists for them, which would defeat the whole thing.
        $this->assertSame(
            0,
            DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('g.name_ar', ['نظام التصنيع', 'الحد الأدنى للطلب'])
                ->where('co.category_id', 0)->count(),
            'a factory-only axis was granted to every root'
        );
    }

    /**
     * Borrowed where borrowing is allowed — and NOT where it was already ruled out.
     *
     * «طباعة مواد تعبئة وتغليف» #232 was going to borrow «تعبئة وتغليف
     * ومستلزمات» from its sibling #204, and `child_option_scopes.php` has
     * declared `232 => []` against that group since 2026-08-08: it PRINTS
     * packaging, it does not SELL it. The declared empty caught the borrow.
     */
    public function test_the_factory_borrowings_take_only_their_half(): void
    {
        $printer = $this->childId('طباعة مواد تعبئة وتغليف');

        $itPrints = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $printer)->where('g.name_ar', 'طباعة العبوات والتغليف')
            ->pluck('o.name_ar')->all();

        $this->assertContains('طباعة على الكرتون', $itPrints);

        $itSells = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $printer)->where('g.name_ar', 'تعبئة وتغليف ومستلزمات')
            ->count();

        $this->assertSame(0, $itSells, 'the printer was handed the supplier list the owner retired it from');

        $fire = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $this->childId('سيفتى ومقاومة حرائق'))
            ->where('g.name_ar', 'أنظمة الأمن والسلامة')
            ->pluck('o.name_ar')->all();

        $this->assertContains('طفايات ومعدات إطفاء', $fire);
        $this->assertContains('أنظمة إنذار الحريق', $fire);

        // The half it does not do. A fire-equipment factory fits no intercom.
        $this->assertNotContains('كاميرات مراقبة', $fire);
        $this->assertNotContains('إنتركم وفيديو إنتركم', $fire);
        $this->assertNotContains('بصمة وحضور وانصراف', $fire);
    }

    /**
     * A duplicate child id in a `links` block is silent, and it has now been
     * written twice by the same hand.
     *
     * PHP keeps the LAST key and discards the earlier entry with no warning, so
     * a child listed once for its trade group and again for a shared axis
     * quietly loses the first. It cannot be detected after the file is parsed —
     * by then there is only one key — so the source itself is read.
     */
    public function test_no_data_file_declares_a_child_twice(): void
    {
        foreach (self::FILES as $file) {
            $source = file_get_contents(database_path("seeders/data/{$file}"));

            $links = strstr($source, "'links' => [");

            if ($links === false) {
                continue;
            }

            preg_match_all('/^        (\d+) =>/m', $links, $matches);

            $ids = $matches[1];

            $this->assertSame(
                array_values(array_unique($ids)),
                $ids,
                "{$file} declares a child twice in `links` — PHP keeps only the last"
            );
        }
    }

    /**
     * «شركات» is BOTH roots at once, so the rule is applied per child.
     *
     * Nine of its mute children carry booking and no retail — they are service
     * companies and the service is the priced row, so `line`. Eight carry
     * retail and their priced rows are catalog products, so `modifier`. A root
     * is not a category of trade; it is where a customer looks.
     */
    public function test_the_companies_root_takes_both_rules(): void
    {
        $map = require database_path('seeders/data/company_child_vocabularies.php');

        $retail = (int) DB::table('platform_services')->where('key', 'retail')->value('id');
        $rootId = (int) DB::table('categories')->where('slug', 'companies')->value('id');

        foreach ($map['groups'] as $group => $spec) {
            foreach ($spec['children'] as $childId) {
                $sellsGoods = DB::table('category_platform_services')
                    ->where('category_id', $rootId)->where('child_id', $childId)
                    ->where('platform_service_id', $retail)->where('is_active', 1)->exists();

                $this->assertSame(
                    $sellsGoods ? 'modifier' : 'line',
                    $spec['price_role'],
                    "«{$group}» on child #{$childId}: a goods trade takes a modifier, a service takes a line"
                );
            }
        }
    }

    /** Seventy-one contractors could not say whether they pour or fit out. */
    public function test_the_largest_mute_child_can_name_its_work(): void
    {
        $lines = $this->lines($this->childId('مقاولات'));

        foreach (['أعمال خرسانية', 'تشطيبات متكاملة', 'أعمال كهروميكانيكا'] as $work) {
            $this->assertContains($work, $lines, "a contractor cannot offer «{$work}»");
        }

        // Infrastructure is the sibling's, and the two lists do not overlap:
        // a road contractor and a fit-out contractor are found by different
        // words or neither search works.
        $infra = $this->lines($this->childId('مقاولات بنية تحتية'));

        $this->assertContains('طرق ورصف', $infra);
        $this->assertSame([], array_values(array_intersect($lines, $infra)));
    }

    /**
     * Axes every trade carries, which therefore say nothing about the trade.
     *
     * Measuring against what is LEFT after these is the only measure that
     * works on both kinds of root: a services child answers with a `line`, a
     * goods child with a `modifier`, and either way it must own SOMETHING.
     */
    private const UNIVERSAL = [
        'حالة المنتج', 'الدفع والسداد', 'التسليم والاستلام', 'الاستبدال والإرجاع',
        'نطاق التعامل', 'نمط تقديم الخدمة', 'نظام التصنيع', 'الحد الأدنى للطلب',
        'نوع العملاء', 'نظام التعاقد', 'ملاءمة المكان',
    ];

    /**
     * Every (root, child) that still cannot name its trade.
     *
     * On 2026-08-11 this held 52 entries across five roots. It now holds ONE,
     * and that one is not a gap: **«مندوب» #243 carries 159 merchants, more
     * than any other child on the platform, and the owner withdrew all
     * thirteen of its options by hand** — «ربع نقل» and «ربع نقل صندوق» among
     * them. Curation, recorded in `category_child_option_decisions`.
     *
     * Keyed by ROOT because the links are per-root: a child can name its trade
     * under «شركات» and be mute under «مصانع», which is what seven factory
     * children were until the mirror pass.
     *
     * The list may only SHRINK.
     *
     * @var array<int,string>
     */
    private const MUTE_TRADES = [
        'shipping-delivery:243',
    ];

    /** @return array<int,string> every (root, child) that owns no trade word */
    private function muteTrades(): array
    {
        $mute = [];

        foreach (
            DB::table('categories as r')->join('category_parent_child as pc', 'pc.parent_id', '=', 'r.id')
                ->distinct()->get(['r.id', 'r.slug']) as $root
        ) {
            foreach (
                DB::table('category_parent_child')->where('parent_id', $root->id)
                    ->pluck('child_id') as $childId
            ) {
                $groups = DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('co.child_id', (int) $childId)
                    // Per-root: shared rows plus this root's own.
                    ->whereIn('co.category_id', [0, $root->id])
                    ->distinct()->pluck('g.name_ar')->all();

                if (array_diff($groups, self::UNIVERSAL) === []) {
                    $mute[] = "{$root->slug}:{$childId}";
                }
            }
        }

        return $mute;
    }

    /**
     * Every child must be describable, everywhere.
     *
     * The trade axis says what a business SELLS; this one says what it is
     * LIKE, and it is what a searcher narrows on. Six of «الصحة»'s seven had
     * none — a patient could compare specialty against specialty and learn
     * nothing about insurance, access or opening hours — and «جيم» had none
     * either. The platform's shared descriptives are all goods-shaped, so
     * neither root had anything to inherit.
     *
     * Unlike the two axes above this needs no debt list: it is at zero.
     */
    public function test_every_child_can_be_described(): void
    {
        $bare = [];

        foreach (
            DB::table('categories as r')->join('category_parent_child as pc', 'pc.parent_id', '=', 'r.id')
                ->distinct()->get(['r.id', 'r.slug']) as $root
        ) {
            foreach (
                DB::table('category_parent_child')->where('parent_id', $root->id)
                    ->pluck('child_id') as $childId
            ) {
                $has = DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('co.child_id', (int) $childId)
                    ->whereIn('co.category_id', [0, $root->id])
                    ->where('g.price_role', 'descriptive')->exists();

                if (! $has) {
                    $bare[] = "{$root->slug}:{$childId}";
                }
            }
        }

        $this->assertSame([], $bare, 'these cannot be described at all: ' . implode('، ', $bare));
    }

    /**
     * A withdrawal is about the CHILD it was made on, not about the root.
     *
     * The owner unticked «الاستبدال والإرجاع» (among five others) from
     * «اكسسوار» #8 under مصانع. Asked whether that meant accessories or meant
     * factories, he said **accessories alone** — so «أجهزة رياضية» #24, which
     * answers that axis as a shop, a showroom and a wholesaler, answers it as
     * a factory too. A treadmill goes back to whoever built it.
     *
     * The pair is the point: one child without the axis, one with, under the
     * same root. A later sweep that decides factories «don't do returns» would
     * take #24's away and this would catch it.
     */
    public function test_a_withdrawal_binds_the_child_it_was_made_on(): void
    {
        $factories = (int) DB::table('categories')->where('slug', 'factories')->value('id');

        $answers = fn (int $childId) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->whereIn('co.category_id', [0, $factories])
            ->where('g.name_ar', 'الاستبدال والإرجاع')->exists();

        $this->assertTrue($answers(24), 'أجهزة رياضية should answer returns as a factory');
        $this->assertFalse($answers(8), 'اكسسوار: the owner withdrew this axis under مصانع');
    }

    /**
     * A kiln fires new brick, so «حالة المنتج» has one answer and is not asked.
     *
     * «طوب» #34 answers جديد · مستعمل as a WHOLESALER under شركات, where used
     * brick off a demolition really does pass through. Under مصانع it does
     * not, and the two axes that DO belong to a kiln — returns and dealing
     * scope — were mirrored across in the same pass.
     *
     * This is the shape of the whole factory walk: even the sediment out in
     * the ADD direction, one child at a time, minus whatever the root cannot
     * actually answer.
     */
    public function test_a_kiln_is_not_asked_whether_its_brick_is_used(): void
    {
        $factories = (int) DB::table('categories')->where('slug', 'factories')->value('id');
        $companies = (int) DB::table('categories')->where('slug', 'companies')->value('id');

        $under = fn (int $root, string $group) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', 34)->whereIn('co.category_id', [0, $root])
            ->where('g.name_ar', $group)->exists();

        $this->assertFalse($under($factories, 'حالة المنتج'), 'a kiln fires new brick only');
        $this->assertTrue($under($companies, 'حالة المنتج'), 'the wholesaler still answers it');

        $this->assertTrue($under($factories, 'الاستبدال والإرجاع'));
        $this->assertTrue($under($factories, 'نطاق التعامل'));
    }

    /**
     * Two spare-parts lists, one letter apart, asking different questions.
     *
     * «نوع قطع الغيار» is which SYSTEM of a car — فرامل، فتيس، زجاج سيارات —
     * and belongs to «قطع غيار سيارات» #44. «قطع الغيار حسب الآلة» is which
     * MACHINE — سيارات، معدات ثقيلة، مصاعد — and belongs to the any-machine
     * wholesaler «قطع غيار» #263. Car parts is ONE of its nine rows, not its
     * trade, so neither child borrows the other's list and neither is retired.
     *
     * The old name «أنواع قطع الغيار» is what made them look like duplicates.
     */
    public function test_the_two_spare_parts_lists_are_different_axes(): void
    {
        $this->assertNull(
            DB::table('option_groups')->where('name_ar', 'أنواع قطع الغيار')->first(),
            'the ambiguous name was renamed to «قطع الغيار حسب الآلة»'
        );

        $holders = fn (string $group) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->distinct()->pluck('co.child_id')->all();

        $this->assertSame([44], $holders('نوع قطع الغيار'), 'the car systems list is the car child\'s');
        $this->assertSame([263], $holders('قطع الغيار حسب الآلة'), 'the machine list is the wholesaler\'s');
    }

    /**
     * The children that carry no modifier, and why each is right.
     *
     * Not a debt — a decision, per root:
     *
     *   مطاعم وكافيهات 6  The line is the menu item and what varies it is a
     *                     variant or an add-on, which the MENU service owns
     *                     (`has_variants`, `has_addons`). An option group here
     *                     would be a second pricing system for one thing.
     *   الصحة 7           The line is the specialty. What would be the modifier
     *                     — seen at home, seen online — is already a booking
     *                     KIND: booking_home_visit, booking_online_consultation.
     *   مزارع سمكية،      Live stock sold by the head or the weight, which the
     *   أرانب             catalog product already carries. No second rate.
     *   سوبر ماركت        Sixteen merchants, FIVE line groups, and it prices by
     *                     product — that is the catalog's job.
     *
     * The list is pinned so a genuinely new gap cannot hide among them.
     *
     * @var array<int,int>
     */
    private const NO_MODIFIER_BY_DESIGN = [
        64, 65, 108, 143, 245, 246,          // مطاعم وكافيهات
        163, 215, 252, 513, 514, 515, 542,   // الصحة
        102, 236,                             // مزارع سمكية، أرانب
        272,                                  // سوبر ماركت
    ];

    /** Nothing may quietly join the list of trades with no price axis. */
    public function test_only_the_named_trades_carry_no_modifier(): void
    {
        $found = [];

        foreach (
            DB::table('categories as r')->join('category_parent_child as pc', 'pc.parent_id', '=', 'r.id')
                ->distinct()->get(['r.id', 'r.slug']) as $root
        ) {
            foreach (
                DB::table('category_parent_child')->where('parent_id', $root->id)
                    ->pluck('child_id') as $childId
            ) {
                $has = DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('co.child_id', (int) $childId)
                    ->whereIn('co.category_id', [0, $root->id])
                    ->where('g.price_role', 'modifier')->exists();

                if (! $has) {
                    $found[] = (int) $childId;
                }
            }
        }

        $found = array_values(array_unique($found));

        $new = array_values(array_diff($found, self::NO_MODIFIER_BY_DESIGN));
        $settled = array_values(array_diff(self::NO_MODIFIER_BY_DESIGN, $found));

        $this->assertSame([], $new, 'these lost their price axis: #' . implode(', #', $new));
        $this->assertSame([], $settled, 'these gained one — take them off the list: #' . implode(', #', $settled));
    }

    /**
     * A missing MODIFIER is not a gap, and this records why.
     *
     * A modifier exists where the same line prices two ways. Six roots report
     * children without one and every case is correct: a restaurant's price
     * varies by menu item, which is the line; a clinic's by specialty, which is
     * the line; a fertiliser wholesaler's by product, which is the catalog.
     * Inventing an axis for them would be the noise this sweep removed.
     *
     * What is asserted is the RULE — that no group with fewer than two options
     * is carried as a modifier, since one possible answer is not a question.
     */
    public function test_no_modifier_asks_a_question_with_one_answer(): void
    {
        $thin = DB::table('option_groups as g')
            ->where('g.price_role', 'modifier')
            ->whereRaw('(select count(*) from options o where o.group_id = g.id) = 1')
            ->whereExists(fn ($q) => $q->from('category_child_option as co')
                ->join('options as o2', 'o2.id', '=', 'co.option_id')
                ->whereColumn('o2.group_id', 'g.id'))
            ->pluck('g.name_ar')->all();

        $this->assertSame([], $thin, 'these modifiers offer one answer: ' . implode('، ', $thin));
    }

    /**
     * A per-root narrowing is possible, and the owner's hand outranks it.
     *
     * «اكسسوار» #8 held the whole twelve-row clothing line under مصانع, so a
     * maker of phone cases was asked whether it makes wedding dresses. It was
     * narrowed to three rows with `prune_links` — which neither
     * `child_option_scopes.php` nor the withdrawal record can express, since
     * both narrow a child under EVERY root at once.
     *
     * **Then the owner withdrew all seventeen options by hand at 20:17**, the
     * three kept rows included. So this no longer asserts what #8 carries —
     * that is his answer, and it changed twice in an hour. What it asserts is
     * that the MECHANISM still works and that a withdrawal is honoured, which
     * is what a test can own.
     */
    public function test_a_root_scoped_narrowing_is_possible_and_a_withdrawal_wins(): void
    {
        $childId = $this->childId('اكسسوار');

        $withdrawn = DB::table(ChildOptionDecisions::TABLE)
            ->where('child_id', $childId)
            ->where('kind', ChildOptionDecisions::WITHDRAWN)
            ->pluck('option_id');

        $this->assertNotEmpty($withdrawn, 'the owner curation of #8 on 2026-08-11 is gone from the record');

        /*
         * ⚠ Checked UNDER THE ROOT HE EDITED, and that asymmetry is the finding.
         *
         * He removed these from «اكسسوار» on the مصانع screen, and the
         * withdrawal record is keyed by CHILD — so it now says «withdrawn» for
         * options the SHOP still carries under ملابس، المحلات and شركات. The
         * rows and the record disagree by design, and a test that asserted
         * «withdrawn implies gone everywhere» would be asserting a bug into
         * existence.
         */
        $factories = (int) DB::table('categories')->where('slug', 'factories')->value('id');

        $this->assertSame(
            0,
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('category_id', $factories)
                ->whereIn('option_id', $withdrawn)->count(),
            'an option the owner withdrew is being offered again under the root he removed it from'
        );

        // And the seeder does not hand them back on its next run.
        DB::beginTransaction();

        try {
            (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

            $this->assertSame(
                0,
                DB::table('category_child_option')
                    ->where('child_id', $childId)->where('category_id', $factories)
                    ->whereIn('option_id', $withdrawn)->count(),
                'the seeder re-granted what the owner took off'
            );
        } finally {
            DB::rollBack();
        }
    }

    /** Nothing new may go mute, anywhere on the platform. */
    public function test_no_new_trade_goes_mute(): void
    {
        $new = array_values(array_diff($this->muteTrades(), self::MUTE_TRADES));

        $this->assertSame([], $new, 'these can no longer name their trade: ' . implode('، ', $new));
    }

    /** And an entry leaves the debt once it is settled. */
    public function test_the_mute_list_holds_only_still_mute_trades(): void
    {
        $settled = array_values(array_diff(self::MUTE_TRADES, $this->muteTrades()));

        $this->assertSame([], $settled, 'settled — take them off MUTE_TRADES: ' . implode('، ', $settled));
    }

    /**
     * A trade must be able to name itself under EVERY root it stands beneath.
     *
     * The links are per-root, so an older seeder that named the roots it cared
     * about leaves the same child mute elsewhere. «نجف» could name the
     * furniture it makes under «شركات» and not under «مصانع» — the chandelier
     * FACTORY, silent, while the wholesaler next door spoke.
     */
    public function test_a_trade_is_not_mute_under_one_root_and_fluent_under_another(): void
    {
        $mute = $this->muteTrades();

        $byChild = [];

        foreach ($mute as $ref) {
            [$slug, $childId] = explode(':', $ref);
            $byChild[(int) $childId][] = $slug;
        }

        $split = [];

        foreach ($byChild as $childId => $slugs) {
            $roots = DB::table('category_parent_child')->where('child_id', $childId)->count();

            if ($roots > count($slugs)) {
                $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');
                $split[] = "{$name}#{$childId} (mute under " . implode('، ', $slugs) . " only)";
            }
        }

        $this->assertSame([], $split, 'one trade, two answers: ' . implode(' · ', $split));
    }

    /**
     * The registrar's office — the owner's second question.
     *
     * Six acts of DOCUMENTATION, which is what separates a مأذون from a lawyer:
     * «تخصصات المحاماة» has «أحوال شخصية وأسرة», and that is litigating a family
     * matter, not entering it in the register.
     */
    public function test_the_registrar_can_name_his_acts(): void
    {
        $childId = $this->childId('مأذون شرعى');

        $lines = $this->lines($childId);

        foreach (['عقد قران', 'توثيق طلاق', 'قائمة منقولات زوجية'] as $act) {
            $this->assertContains($act, $lines, "a registrar cannot record «{$act}»");
        }

        // Coming to the house is a price on the same act, not a fourth act.
        $where = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)->where('g.name_ar', 'مكان العقد')
            ->pluck('o.name_ar')->all();

        $this->assertContains('بالمنزل', $where);

        foreach ($lines as $line) {
            $this->assertStringNotContainsString('بالمنزل', $line, 'the location was enumerated as an act');
        }

        // And the six he took off on 2026-08-10 are still off.
        foreach (['كاش', 'تقسيط', 'فردي', 'فريق عمل', 'أونلاين', 'خاص'] as $withdrawn) {
            $this->assertNotContains(
                $withdrawn,
                DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->where('co.child_id', $childId)->pluck('o.name_ar')->all(),
                "«{$withdrawn}» came back to a child the owner had stripped"
            );
        }
    }

    /**
     * The systems half and the manpower half never repeat each other.
     *
     * «أمن وسلامة» #254 under تكنولوجيا installs the cameras; «أمن» #253 under
     * مكاتب sends the guards. The market sells them together and the platform
     * keeps them apart, so a business doing both stands as two.
     */
    public function test_security_systems_and_security_guards_stay_apart(): void
    {
        $systems = $this->lines($this->childId('أمن وسلامة'));
        $guards = $this->lines($this->childId('أمن'));

        $this->assertContains('كاميرات مراقبة', $systems);
        $this->assertContains('أنظمة إنذار الحريق', $systems);
        $this->assertContains('حراسة مقرات ومنشآت', $guards);
        $this->assertContains('نقل أموال', $guards);

        $this->assertSame(
            [],
            array_values(array_intersect($systems, $guards)),
            'the two halves of security are selling the same row'
        );
    }

    /** A firewall is written, not bolted to a wall. */
    public function test_the_technology_trades_do_not_overlap(): void
    {
        $software = $this->lines($this->childId('برمجة'));
        $telecom = $this->lines($this->childId('إتصالات'));

        $this->assertContains('أمن معلومات وحماية سيبرانية', $software);
        $this->assertNotContains('أمن معلومات وحماية سيبرانية', $this->lines($this->childId('أمن وسلامة')));

        $this->assertContains('شبكات وواي فاي', $telecom);

        // «دش وأقمار صناعية» is a trade of its own under «مهن وحرفيين».
        foreach ($telecom as $line) {
            $this->assertStringNotContainsString('دش', $line, 'satellite work belongs to child #251');
        }

        $this->assertSame([], array_values(array_intersect($software, $telecom)));
    }

    /** The owner's question: a home-services office is an agency. */
    public function test_a_home_services_office_sells_household_work(): void
    {
        $lines = $this->lines($this->childId('خدمات منزلية'));

        foreach (['تنظيف منازل', 'تنظيف خزانات', 'مكافحة حشرات', 'جليسة أطفال', 'رعاية مسنين'] as $service) {
            $this->assertContains($service, $lines, "a home-services office cannot offer «{$service}»");
        }

        /*
         * And it is not a craftsman. نقاش، سباك and كهربائي are children of
         * «مهن وحرفيين» in their own right; repeating their work here would
         * sell one job under two trades and split every search for it.
         */
        foreach (['نقاش', 'سباكة', 'كهرباء'] as $craft) {
            $this->assertNotContains($craft, $lines, "«{$craft}» is a trade of its own, not a line here");
        }

        // A maid by the visit and a maid living in are two prices for one line.
        $modifiers = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $this->childId('خدمات منزلية'))
            ->where('g.name_ar', 'نظام التعاقد')->pluck('o.name_ar')->all();

        $this->assertContains('بالزيارة', $modifiers);
        $this->assertContains('بالإقامة', $modifiers);

        // The desk's plans are not the maid's.
        $this->assertNotContains('بالساعة', $modifiers);
    }

    /** «إدارة صفحات» borrows the advertising list rather than cloning it. */
    public function test_page_management_borrows_the_digital_half(): void
    {
        $lines = $this->lines($this->childId('إدارة صفحات'));

        $this->assertContains('تسويق رقمي وسوشيال ميديا', $lines);
        $this->assertContains('إعلانات ممولة', $lines);

        // The physical half stays with «دعاية وإعلان» — that IS the difference.
        $this->assertNotContains('لافتات وإعلانات طرق', $lines);
        $this->assertNotContains('مطبوعات دعائية', $lines);

        // And the advertising child keeps all seven.
        $this->assertCount(7, $this->lines($this->childId('دعاية وإعلان')));
    }

    /** One trade, one vocabulary, whichever root the customer came through. */
    public function test_a_trade_under_two_roots_answers_the_same(): void
    {
        foreach (['طباعة', 'أمن', 'تنسيق حفلات'] as $name) {
            $childId = $this->childId($name);

            $roots = DB::table('category_parent_child')->where('child_id', $childId)->count();

            $this->assertGreaterThan(1, $roots, "«{$name}» is expected under more than one root");

            $shared = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', $childId)->where('g.price_role', 'line')
                ->where('co.category_id', 0)->exists();

            $this->assertTrue($shared, "«{$name}»'s trade vocabulary is tied to one root");
        }
    }

    /** A withdrawn option is never handed back. */
    public function test_the_seeder_refuses_what_the_owner_withdrew(): void
    {
        $childId = $this->childId('خدمات منزلية');

        $optionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'الخدمات المنزلية')->where('o.name_ar', 'تنظيف منازل')->value('o.id');

        DB::table('category_child_option')
            ->where('child_id', $childId)->where('option_id', $optionId)->delete();

        DB::table(ChildOptionDecisions::TABLE)->insert([
            'child_id' => $childId,
            'option_id' => $optionId,
            'kind' => ChildOptionDecisions::WITHDRAWN,
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

        $this->assertFalse(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            'the seeder handed back an option the owner had taken off'
        );
    }

    /** Registered in option_price_roles.php, or reset on the next run. */
    public function test_the_new_groups_keep_their_role(): void
    {
        $declared = require database_path('seeders/data/option_price_roles.php');

        // group name => the role the seeder writes, across every data file it
        // reads. A group that prices and is not declared is reset the next time
        // OptionPriceRolesSeeder runs, silently — six times so far.
        $roles = [];

        foreach (['office_child_vocabularies.php', 'technology_child_vocabularies.php'] as $file) {
            foreach ((require database_path("seeders/data/{$file}"))['groups'] ?? [] as $group => $spec) {
                $roles[$group] = $spec['price_role'];
            }
        }

        foreach ($roles as $group => $role) {
            if ($role === 'descriptive') {
                // The file's own default, and the only role that needs no
                // entry. What it must NOT be is claimed by a priced tier.
                $this->assertNotContains($group, $declared['line'], "«{$group}» is declared as a line");
                $this->assertNotContains($group, $declared['modifier'], "«{$group}» is declared as a modifier");

                continue;
            }

            $this->assertContains(
                $group,
                $declared[$role] ?? [],
                "«{$group}» is not declared as `{$role}` and will be reset"
            );
        }

        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            foreach ($roles as $group => $role) {
                $this->assertSame(
                    $role,
                    DB::table('option_groups')->where('name_ar', $group)->value('price_role'),
                    "«{$group}» lost its role"
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running it changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')->count();

        (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->count());
    }
}
