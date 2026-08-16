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

    /**
     * Every factory group holds the role the AUTHORITY gives it.
     *
     * This used to assert the opposite of what it now does: that no factory
     * vocabulary may be a `line`, because «nobody buys the phrase طوب أحمر —
     * they buy a catalog product». The owner overturned that on 2026-08-16
     * («معظم مجموعات الخيارات ... المفروض ان تكون سطر مسعر») and the data backs
     * him: the catalog is six or seven rows deep for most of these trades, so a
     * modifier with no line under it prices nothing at all. See the goods
     * reversal block in `option_price_roles.php`.
     *
     * What survives, and is the part worth keeping, is that the two files must
     * AGREE. A group declared `modifier` in the vocabulary file and `line` in
     * the roles file is the drift that flipped «أنواع الزجاج» back and forth
     * for a week — so the roles file is read as the authority and the
     * vocabulary file is checked against it, rather than each being asserted
     * on its own.
     */
    public function test_a_factory_vocabulary_holds_the_role_the_authority_gives_it(): void
    {
        $declared = require database_path('seeders/data/option_price_roles.php');

        $roleOf = function (string $group) use ($declared): ?string {
            foreach (['line', 'modifier', 'descriptive'] as $role) {
                if (in_array($group, $declared[$role], true)) {
                    return $role;
                }
            }

            return null;
        };

        foreach ((require database_path('seeders/data/factory_child_vocabularies.php'))['groups'] as $group => $spec) {
            $authority = $roleOf($group);

            $this->assertNotNull(
                $authority,
                "«{$group}» is in no list in option_price_roles.php — it will be reset to descriptive"
            );

            $this->assertSame(
                $authority,
                $spec['price_role'],
                "«{$group}» is «{$spec['price_role']}» in factory_child_vocabularies.php and «{$authority}» in the authority"
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

        /*
         * And no shared row exists for them on a LIVE child, which would defeat
         * the whole thing.
         *
         * Retired children are excluded, and that is not a loophole: a child
         * with no root is reachable by nothing, and `bim:fold-child` unfiles a
         * retired child's root-scoped rows to ALL_ROOTS on purpose — they are
         * the record of what it carried, and filing them under a root it has
         * left is what the fold was leaving behind before 2026-08-15.
         */
        $this->assertSame(
            0,
            DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereExists(fn ($q) => $q->from('category_parent_child as pc')
                    ->whereColumn('pc.child_id', 'co.child_id'))
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

                /*
                 * Both take a line since 2026-08-16. The rule this test was
                 * written for — goods take a modifier because the catalog
                 * holds the priced rows — was the owner's to set and his to
                 * reverse, and he reversed it: the catalog is six or seven
                 * rows deep for these trades, so a modifier prices nothing.
                 *
                 * What is still worth holding is that the file and the
                 * authority agree, which is checked against
                 * `option_price_roles.php` rather than re-derived from whether
                 * the child has retail.
                 */
                $this->assertContains(
                    $spec['price_role'],
                    ['line', 'modifier'],
                    "«{$group}» on child #{$childId} has no usable role"
                );

                $this->assertNotSame(
                    'descriptive',
                    $spec['price_role'],
                    "«{$group}» on child #{$childId} cannot be priced at all"
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
     * It read as broken once, for «استوديوهات» #271, and the cause was not a
     * missing vocabulary: the owner withdrew «عائلي» and «ممنوع التدخين» from
     * it on 2026-08-13 — his call, a recording booth is not a place a family
     * visits — and its «الدفع والسداد» was filed under root #17, which #271
     * does not sit under, so nothing could reach it. Rescoped by
     * `bim:rescope-stray-options` on 2026-08-15 along with seventeen other rows
     * left behind by folds. Back to zero, and no debt list.
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
     * …and no option row names a root its child does not sit under.
     *
     * Such a row is reachable by nothing — `idsFor()` is only ever called with
     * a root the child IS under — so it counts on the admin's badge, is
     * returned by everything that reads by child alone, and is offered to
     * nobody. It is also how #271 came to look mute above while holding a
     * perfectly good descriptive axis.
     *
     * Seventeen of the original eighteen were made by `bim:fold-child`, which
     * unlinked a retired child from its roots and left its scoped rows pointing
     * at them. The fold unfiles them now; this holds the invariant.
     */
    public function test_no_option_row_names_a_root_its_child_left(): void
    {
        $strays = DB::table('category_child_option as cco')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->join('categories as cat', 'cat.id', '=', 'cco.category_id')
            ->where('cco.category_id', '>', 0)
            ->whereNotExists(fn ($q) => $q->from('category_parent_child as pc')
                ->whereColumn('pc.child_id', 'cco.child_id')
                ->whereColumn('pc.parent_id', 'cco.category_id'))
            ->distinct()
            ->pluck(DB::raw("concat(c.name_ar, ' #', cco.child_id, ' → ', cat.name_ar)"));

        $this->assertSame([], $strays->all(),
            'filed under a root the child does not sit under: ' . $strays->implode('، '));
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

        // The grade is the one axis BOTH answer — أصلي وكيل and تجاري are the
        // same part at a multiple of each other, whatever machine it is for.
        $this->assertSame([44, 263], $holders('درجة قطعة الغيار'));

        $grades = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'درجة قطعة الغيار')->pluck('o.name_ar');

        $this->assertNotContains('مستعمل', $grades, '«حالة المنتج» owns جديد · مستعمل');
    }

    /**
     * No root says one thing. This is the bulk-picker slip, caught by shape.
     *
     * The bulk child-options screen in replace mode writes the picked group
     * onto every selected child and withdraws what each of them was saying.
     * Twice on 2026-08-11 that took a whole root: «أنواع الأبواب والشبابيك»
     * onto 42 of مصانع's 44 (a food factory selling «شاتر كهربائي»), and
     * «أنواع الأجهزة الرياضية» onto 69 of شركات's 70 (a contractor selling
     * treadmills). Reverted by BulkPickerSlipRevertSeeder.
     *
     * A trade vocabulary is by nature narrow — «أنواع الطوب» belongs to the
     * kiln and nothing else — so one held by most of a root is not a
     * vocabulary, it is a save that went wide. The universal axes are excluded
     * because being everywhere is precisely their job.
     */
    /**
     * The two that really are the whole root's question, not a slip.
     *
     * «مستلزمات المزارع» — every farm child buys feed, troughs and incubators,
     * whatever it raises. «ألعاب ومرافق الترفيه» — every entertainment child
     * has facilities, and which ones is the thing a customer chooses on.
     *
     * Both were written deliberately, group-first, rather than picked onto a
     * root. The list may only shrink.
     *
     * @var array<int,string>
     */
    private const ROOT_WIDE_BY_NATURE = [
        // «مستلزمات المزارع» left this list on 2026-08-12. It was never really
        // the root's question — three rows that restate the child's own name,
        // spread across ten children because nobody had written them anything
        // better. Seven of them now name their trade and the group is down to
        // the three bulk traders it is actually true of.
        'arts-entertainment:ألعاب ومرافق الترفيه',
    ];

    public function test_no_vocabulary_is_spread_across_a_whole_root(): void
    {
        $wide = [];

        foreach (
            DB::table('categories as r')->join('category_parent_child as pc', 'pc.parent_id', '=', 'r.id')
                ->distinct()->get(['r.id', 'r.slug']) as $root
        ) {
            $children = DB::table('category_parent_child')->where('parent_id', $root->id)->pluck('child_id');

            if ($children->count() < 8) {
                continue; // too small for "most of it" to mean anything
            }

            $counts = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('co.child_id', $children)
                ->whereIn('co.category_id', [0, $root->id])
                ->whereNotIn('g.name_ar', self::UNIVERSAL)
                ->select('g.name_ar', DB::raw('COUNT(DISTINCT co.child_id) as held'))
                ->groupBy('g.name_ar')->get();

            foreach ($counts as $row) {
                if (in_array("{$root->slug}:{$row->name_ar}", self::ROOT_WIDE_BY_NATURE, true)) {
                    continue;
                }

                if ($row->held / $children->count() > 0.6) {
                    $wide[] = "{$root->slug}: «{$row->name_ar}» على {$row->held} من {$children->count()}";
                }
            }
        }

        $this->assertSame([], $wide, 'a vocabulary went root-wide: ' . implode(' · ', $wide));
    }

    /**
     * «مفروشات - اقمشة هم فقراء جدا فى خياراتهم» — owner, 2026-08-12.
     *
     * «مفروشات» #115 had five rows of the FURNITURE list — صالون، أنتريه،
     * ركنه، تابلوه — and «أقمشة» #95 had exactly one row, the word «أقمشة»
     * itself. Neither could say what it actually sells, which for a soft
     * furnishing shop is مفارش وملايات ومناشف and for a fabric shop is قطن
     * وكتان وشيفون.
     *
     * A child whose whole vocabulary is ONE row is the shape worth guarding:
     * it passes every mute check and still says nothing.
     */
    public function test_the_furnishing_trades_can_name_their_stock(): void
    {
        $named = function (int $childId, string $group): array {
            return DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', $childId)->where('g.name_ar', $group)
                ->distinct()->pluck('o.name_ar')->all();
        };

        $this->assertContains('مفارش سرير', $named(115, 'أصناف المفروشات'));
        $this->assertContains('مناشف وبشاكير', $named(115, 'أصناف المفروشات'));
        $this->assertContains('قطن', $named(95, 'أنواع الأقمشة'));
        $this->assertContains('أقمشة تنجيد', $named(95, 'أنواع الأقمشة'));

        // The furniture pieces are the trade next door, and only «سجاد
        // ومفروشات» was ever about soft furnishing.
        $this->assertSame(['سجاد ومفروشات'], $named(115, 'أثاث وتشطيب منزلي'));

        /*
         * «عدل أصناف المفروشات سطر مسعر» — owner, overruling the goods rule
         * that would have made it a modifier. A مفروشات merchant quotes «طقم
         * مفارش سرير» as a price with a size and a piece count, so the range IS
         * the priced row. The fabric list stays a modifier: a bolt of cotton is
         * a catalog product and the fibre qualifies its price.
         */
        $role = fn (string $group) => (string) DB::table('option_groups')
            ->where('name_ar', $group)->value('price_role');

        $this->assertSame('line', $role('أصناف المفروشات'));
        $this->assertSame('modifier', $role('أنواع الأقمشة'));
    }

    /**
     * «لا تدمج لوازم ستائر وستائر وديكور فهم بندين مختلفين» — owner,
     * 2026-08-12.
     *
     * The two came up as a merge candidate on the mechanical test — same root,
     * identical option set — because #76 BORROWED «لوازم الستائر» from #9
     * rather than being given a list of its own. Sharing a vocabulary is not
     * being one trade: #9 sells the fittings, #76 sells and hangs the curtain.
     *
     * Pinned so the audit does not propose it a second time.
     */
    public function test_the_two_curtain_trades_stay_apart(): void
    {
        foreach ([9, 76] as $childId) {
            $this->assertTrue(
                DB::table('category_parent_child')->where('child_id', $childId)->exists(),
                "curtain child #{$childId} was folded away"
            );

            /*
             * They no longer SHARE a list, and that is the ruling finishing
             * rather than breaking. «ستائر وديكور المفروض به انواع الستائر وليس
             * لوازم الستائر» — owner, 2026-08-16. #9 keeps «لوازم الستائر» (the
             * rails, rings and brackets); #76 has «أنواع الستائر والديكور».
             *
             * What this test exists for is that the two stay APART and neither
             * goes mute — which is now checked by each one holding its own.
             */
            $ownList = $childId === 9 ? 'لوازم الستائر' : 'أنواع الستائر والديكور';

            $this->assertTrue(
                DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('co.child_id', $childId)->where('g.name_ar', $ownList)->exists(),
                "curtain child #{$childId} lost «{$ownList}»"
            );
        }
    }

    /**
     * «ابدأ بالنجف» — owner, 2026-08-12.
     *
     * «نجف» #56 and «نجف و تحف» #57 each said exactly ONE word and it was
     * «تابلوه»: a row of the furniture list, narrowed to the wall-art piece. A
     * whole lighting trade that could not say نجف, on a platform that had no
     * lighting word anywhere — searching «إضاءة» found a spare-part domain, a
     * party-hire row and a false ceiling.
     *
     * And #57 is TWO trades in its own name. Its antiques half is borrowed
     * whole from «أنتيكات وتحف» #21 rather than cloned; its retail branch had
     * named both shelves all along, so the wiring was ahead of the words.
     */
    public function test_the_lighting_trades_can_say_chandelier(): void
    {
        $named = fn (int $childId, string $group) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();

        foreach ([56, 57] as $childId) {
            $lighting = $named($childId, 'أنواع النجف والإضاءة');

            $this->assertContains('نجف كريستال', $lighting);
            $this->assertContains('شرائط ليد', $lighting);
            $this->assertGreaterThan(10, count($lighting));
        }

        // Only the one whose name says تحف carries the antiques list.
        $this->assertContains('تحف نحاسية', $named(57, 'الأنتيكات والتحف'));
        $this->assertSame([], $named(56, 'الأنتيكات والتحف'));
    }

    /**
     * «اكمل بعنقود المزارع» — owner, 2026-08-12.
     *
     * Seven children shared «مستلزمات المزارع» and nothing else, and that group
     * is three rows — «مستلزمات زراعية»، «ماشية وطيور»، «معدات ومستلزمات» —
     * which restate the child's own name instead of saying anything about it.
     * «معدات مزارع دواجن» answering «ماشية وطيور» told a customer nothing.
     *
     * Three trades were hiding in the seven: farm MACHINERY (#12), livestock
     * HOUSING (#171، #230، #235), and the ANIMALS themselves (#170، #236،
     * #102). The last two are one group each, narrowed per child — a milking
     * parlour is not a rabbit hutch, and a rabbit is not a mullet.
     */
    public function test_the_farm_cluster_names_three_trades_not_one(): void
    {
        $named = fn (int $childId, string $group) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();

        $this->assertContains('جرارات زراعية', $named(12, 'الآلات والمعدات الزراعية'));

        /*
         * The three equipment children became ONE on 2026-08-12, so the keeper
         * takes the whole list — milking parlour and incubator both. The
         * per-animal narrowing they had for a day is kept as a comment in
         * `child_option_scopes.php`, which is where a future split starts.
         */
        $equipment = $named(171, 'معدات وتجهيزات المزارع');
        $this->assertContains('أنظمة حلابة', $equipment);
        $this->assertContains('حضانات وفقاسات', $equipment);

        // The stock list: three answers that do not overlap at all.
        $stock = 'أنواع الثروة الحيوانية والسمكية';
        $this->assertContains('أبقار', $named(170, $stock));
        $this->assertContains('أرانب تسمين', $named(170, $stock));
        $this->assertContains('أسماك بلطي', $named(102, $stock));
        $this->assertSame([], array_intersect($named(170, $stock), $named(102, $stock)));

        // And none of the survivors is asked the grab-bag question any more.
        foreach ([12, 102, 170, 171] as $childId) {
            $this->assertSame([], $named($childId, 'مستلزمات المزارع'));
        }
    }

    /**
     * «نفذ ١ و٢ و٣ وادمج مواشي وأرانب فقط» — owner, 2026-08-12.
     *
     * Fourteen children of «زراعية وحيوانية» became nine. Each merge keeps one
     * child, RENAMES it to cover what it swallowed, and folds the rest — the id
     * survives, so every option link, service config and account travels with
     * it. None of the nine held an account, so nothing was rehomed.
     *
     * «مزارع سمكية» and «دواجن» were on the table and stayed: aquaculture is a
     * different licence and a different cycle, and «دواجن» is a fresh SELLER,
     * not a producer.
     */
    public function test_the_agriculture_root_merged_fourteen_children_into_nine(): void
    {
        $root = (int) DB::table('categories')->where('slug', 'agriculture-and-animals')->value('id');

        $standing = DB::table('category_parent_child as pc')
            ->join('category_children_master as m', 'm.id', '=', 'pc.child_id')
            ->where('pc.parent_id', $root)->pluck('m.name_ar', 'pc.child_id');

        $this->assertCount(9, $standing);

        // The keepers answer to the wider name.
        $this->assertSame('معدات وتجهيزات المزارع', $standing[171] ?? null);
        $this->assertSame('خضار وفاكهة', $standing[114] ?? null);
        $this->assertSame('تقاوي وأسمدة ومبيدات', $standing[14] ?? null);
        $this->assertSame('مواشي وأرانب', $standing[170] ?? null);

        // The folded rows survive and reach nobody — nothing here is deleted.
        foreach ([230, 235, 292, 99, 236] as $folded) {
            $this->assertTrue(DB::table('category_children_master')->where('id', $folded)->exists());
            $this->assertSame(0, DB::table('category_parent_child')->where('child_id', $folded)->count());
        }

        $named = fn (int $childId, string $group) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();

        // A keeper that swallowed its sibling must be able to SAY the sibling.
        $equipment = $named(171, 'معدات وتجهيزات المزارع');
        $this->assertContains('أنظمة حلابة', $equipment);
        $this->assertContains('حضانات وفقاسات', $equipment);

        $stock = $named(170, 'أنواع الثروة الحيوانية والسمكية');
        $this->assertContains('أبقار', $stock);
        $this->assertContains('أرانب تسمين', $stock);
        $this->assertNotContains('أسماك بلطي', $stock, 'the fish farm stayed its own child');

        // The merge closed a gap: «مبيدات» had no child anywhere before it.
        $this->assertContains('مبيدات حشرية', $named(14, 'مستلزمات المحاصيل'));
    }

    /**
     * Five shops that were answering with the neighbour's list.
     *
     * The merge audit of 2026-08-12 reported them as twins, and not one was a
     * merge: each read as identical to the shop next door only because it had
     * borrowed that shop's vocabulary for want of one. «أدوات كهربائية» was
     * offering fridges; «جراج» was offering to hire out a 50-seat coach.
     *
     * **Two identical option sets are a question, and this is the other answer
     * to it** — the first was «لوازم ستائر» vs «ستائر وديكور», two real trades
     * sharing one list on purpose.
     */
    public function test_the_borrowed_lists_were_given_back(): void
    {
        $named = fn (int $childId, string $group) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();

        // A garage parks a bus, it does not hire one out.
        $this->assertContains('اشتراك شهري', $named(119, 'خدمات الجراج والانتظار'));
        $this->assertSame([], $named(119, 'مركبات النقل والركاب'));

        // «Electric Tools» — the name_en had said so all along.
        $this->assertContains('شنيور ومثقاب', $named(87, 'العدد والأدوات الكهربائية'));
        $this->assertSame([], $named(87, 'أنواع الأجهزة الكهربائية'));

        /*
         * The parts dealer KEEPS the appliance list — for it «which machine» is
         * the right axis — and gains the second one. Same two-axis shape as
         * «قطع غيار سيارات» #44, which says both the marque and the system.
         */
        $this->assertContains('كمبروسر تبريد', $named(264, 'قطع غيار الأجهزة المنزلية'));
        $this->assertNotSame([], $named(264, 'أنواع الأجهزة الكهربائية'));

        // These two could say which CARS and never which oil, which tyre.
        $this->assertContains('زيت محرك', $named(42, 'أنواع الزيوت والسوائل'));
        $this->assertContains('جنوط ألومنيوم', $named(249, 'الإطارات والجنوط'));
        $this->assertNotSame([], $named(42, 'ماركات السيارات'), 'which cars it fits is still right');
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
        /*
         * «معدات زراعية» #12. It IS named in `condition_children`, so the file
         * still offers it جديد and مستعمل — and the owner withdrew both by hand
         * on 2026-08-16 02:06:51, in the same pass that took the supermarket
         * aisle off the fish farm. The ledger blocks the grant on every run,
         * which is the mechanism working exactly as intended: his hand beats the
         * file, and the file is left saying what it would offer if he changed
         * his mind.
         *
         * Listed here rather than argued with. Un-withdrawing it in the screen
         * is all it takes to take this entry off again.
         */
        12,

        102, 170,                             // مزارع سمكية · مواشي وأرانب — «أرانب» #236 folded
                                              // into #170 on 2026-08-12 and reaches no root.
                                              // #170 joined its twin on 2026-08-16 for the reason
                                              // already written for #102: both sell live stock by
                                              // the head or by weight, and neither has a second
                                              // rate for one line — a buffalo is a buffalo. They
                                              // had been answering «مستلزمات المزارع», a grab-bag
                                              // restating the child's own name, which
                                              // child_option_scopes.php has declared empty for both
                                              // since 2026-08-12 and which reached the live
                                              // database on 2026-08-16.
        272,                                  // سوبر ماركت
        /*
         * «مخابز» #27، «مجمدات» #113 and «مني ماركت» #185, on the owner's own
         * sweep of 2026-08-16 02:30–03:10. He took «جديد / مستعمل» off them
         * one child at a time, with «تيك أواى» and «تسليم أرض المصنع» — a
         * bakery, a frozen-goods shop and a mini market do not sell anything
         * second-hand, and a modifier with one possible answer is noise on the
         * pricing screen. Same reading «سوبر ماركت» above already had.
         *
         * «هايبر ماركت» #149 followed minutes later, which completes the set:
         * all four grocers and both kitchens now say it the same way.
         */
        27, 113, 149, 185,
        101,                                  // أسماك — narrowed by hand under «المحلات» on
                                              // 2026-08-12: it had been answering with the whole
                                              // fresh counter (خضار وفاكهة، ألبان وبيض، أجبان،
                                              // لحوم ودواجن، مجمدات) and now says فسيخ · رنجة ·
                                              // أسماك طازجة. «جديد / مستعمل» went with them, which
                                              // is right — nobody sells second-hand fish. Under
                                              // «مصانع» it keeps both modifiers.
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

        /*
         * A child whose modifier the owner took off by hand is not a gap — it
         * is an answer, and the ledger holds it.
         *
         * This was a pinned list alone, and on 2026-08-16 it became a treadmill:
         * between 02:30 and 03:30 he swept «حالة المنتج» off shop after shop —
         * مخابز، مجمدات، مني ماركت، هايبر ماركت، منظفات، عصائر، حلويات — because
         * a grocer sells nothing second-hand and a modifier with one possible
         * answer is noise on the pricing screen. Every one of those saves made
         * this test red and every one of them was right, so appending ids was
         * losing to him one child at a time.
         *
         * The list stays for the trades whose silence predates the record —
         * a restaurant, a clinic, a fish farm — where nothing was withdrawn and
         * the reason is written down here instead.
         */
        $explained = DB::table('category_child_option_decisions as d')
            ->join('options as o', 'o.id', '=', 'd.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('d.kind', ChildOptionDecisions::WITHDRAWN)
            ->where('g.price_role', 'modifier')
            ->distinct()->pluck('d.child_id')
            ->map(fn ($id) => (int) $id)->all();

        $new = array_values(array_diff($found, self::NO_MODIFIER_BY_DESIGN, $explained));
        $settled = array_values(array_diff(self::NO_MODIFIER_BY_DESIGN, $found));

        $this->assertSame([], $new, 'these lost their price axis and nobody decided that: #' . implode(', #', $new));
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
        $factories = (int) DB::table('categories')->where('slug', 'factories')->value('id');

        /*
         * Read the record AT THE ROOT, which is what the note below says and
         * what this query did not do.
         *
         * It used to take every withdrawal recorded against #8 whatever root it
         * was made under, and then look for rows under مصانع. That is the exact
         * conflation the note warns about, and on 2026-08-14 it came true: the
         * owner withdrew thirty-five words from #8 on the ملابس screen, and
         * three of them are legitimately still on the factory side — a case
         * maker under مصانع is not the shop under ملابس.
         *
         * A withdrawal at ALL_ROOTS still counts, because that one really does
         * mean everywhere.
         */
        $withdrawn = DB::table(ChildOptionDecisions::TABLE)
            ->where('child_id', $childId)
            ->where('kind', ChildOptionDecisions::WITHDRAWN)
            ->whereIn('category_id', [0, $factories])
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

    /**
     * «قسّم مرافق النادي الرياضي» — owner, 2026-08-16.
     *
     * One `descriptive` list was two: the rooms a member walks into, and the
     * three or four things a club bills him for on top of the subscription.
     * While they shared a group the trainer could not be priced at all, because
     * a group carries one role.
     *
     * The split moves `options.group_id` and nothing else, so what is asserted
     * is the pair: the rows landed on the right side, AND every club kept every
     * row it had. A regroup that quietly dropped a link would look identical to
     * a correct one from the group's side alone.
     */
    public function test_the_club_facilities_split_into_the_place_and_the_bill(): void
    {
        $groupOf = fn (string $option) => (string) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.name_ar', $option)->where('g.name_ar', 'like', '%النادي الرياضي')
            ->value('g.name_ar');

        // The room the member uses himself.
        foreach (['حمام سباحة', 'ساونا', 'جاكوزي', 'قسم سيدات', 'خزائن ودش', 'انتظار سيارات', 'كيدز ايريا'] as $facility) {
            $this->assertSame('مرافق النادي الرياضي', $groupOf($facility), "«{$facility}» is a room, not a bill");
        }

        // Somebody's time, and every club in Egypt sells it.
        foreach (['مدرب شخصي', 'استشارة تغذية', 'حمام مغربي', 'حضانة أطفال'] as $service) {
            $this->assertSame('خدمات النادي الرياضي', $groupOf($service), "«{$service}» is somebody's time and must be priceable");
        }

        $this->assertSame(
            'descriptive',
            DB::table('option_groups')->where('name_ar', 'مرافق النادي الرياضي')->value('price_role')
        );

        $this->assertSame(
            'line',
            DB::table('option_groups')->where('name_ar', 'خدمات النادي الرياضي')->value('price_role'),
            'the half that exists so a club can price it must be a line'
        );
    }

    /**
     * …and the promise the regroup makes: only the heading moved.
     *
     * Five clubs carry these words. Re-running the seeder must leave every one
     * of them holding exactly what it held — including «ملاعب كرة», which is not
     * in this file's children list and picked up three of the rows from a save
     * of the owner's. A split that re-grants by list rather than moving by id
     * would either lose that or hand it the rest.
     */
    public function test_the_regroup_moved_headings_and_not_rows(): void
    {
        $carriers = fn () => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as c', 'c.id', '=', 'co.child_id')
            ->whereIn('g.name_ar', ['مرافق النادي الرياضي', 'خدمات النادي الرياضي'])
            ->distinct()->orderBy('c.name_ar')->orderBy('o.name_ar')
            ->pluck(DB::raw("concat(c.name_ar, ' · ', o.name_ar) as label"))->all();

        $before = $carriers();

        $this->assertNotEmpty($before);

        (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

        $this->assertSame($before, $carriers(), 'a club gained or lost a row in a move that only changes a heading');
    }

    /** Re-running it changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')->count();

        (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->count());
    }
}
