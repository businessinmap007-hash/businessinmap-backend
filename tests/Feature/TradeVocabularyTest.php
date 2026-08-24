<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «قم باضافة مركز حجامة فى المصحة … قائمة خيارات بالمنتجات الغذائية … وايضا
 * قائمة بالاجهزة الكهربائية والرياضية. بنفس نمط ماركات السيارات والصحة» —
 * owner, 2026-08-09.
 *
 * Three of the four groups are the car-brands pattern exactly: ONE multi-select
 * `modifier` list saying what a trade stocks, shared across every root the child
 * stands under. The fourth is the one the owner singled out — «خيارات قابلة
 * للتسعير» — so it is `line`: a cupping session is booked and paid for.
 */
class TradeVocabularyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The child of that name a customer can REACH.
     *
     * Not `where(name_ar)->value('id')`: several names have two master rows and
     * the lowest id is the RETIRED twin — «أجهزة رياضية» #7 is detached, #24 is
     * live. Resolving naively is how the seeder first hung fifteen sports
     * options on a child hanging from no root, and a test that resolves the same
     * wrong way would have called it correct.
     */
    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master as c')
            ->join('category_parent_child as p', 'p.child_id', '=', 'c.id')
            ->where('c.name_ar', $nameAr)
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM users u WHERE u.category_child_id = c.id)'))
            ->orderBy('c.id')
            ->value('c.id');
    }

    /** @return array<int,string> */
    private function optionsOf(string $childNameAr, string $groupNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($childNameAr))
            ->where('g.name_ar', $groupNameAr)
            ->pluck('o.name_ar')->all();
    }

    /** The new child exists, under الصحة, beside the clinics. */
    public function test_the_cupping_centre_stands_in_the_health_root(): void
    {
        $childId = $this->childId('مركز حجامة');
        $health = (int) DB::table('categories')->where('slug', 'health')->value('id');

        $this->assertGreaterThan(0, $childId, '«مركز حجامة» was never created');
        $this->assertTrue(
            DB::table('category_parent_child')->where('parent_id', $health)->where('child_id', $childId)->exists()
        );
    }

    /** And it can be booked, on the same terms a clinic is. */
    public function test_it_is_booked_the_way_a_clinic_is(): void
    {
        $health = (int) DB::table('categories')->where('slug', 'health')->value('id');
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $config = fn (int $childId) => json_decode((string) DB::table('category_service_configs')
            ->where('category_id', $health)->where('child_id', $childId)
            ->where('platform_service_id', $booking)->where('is_active', 1)->value('config'), true) ?: [];

        $mine = $config($this->childId('مركز حجامة'));

        $this->assertNotSame([], $mine, 'the cupping centre cannot be booked at all');

        // Compared as a SET. `BookingChildKindsSeeder` rewrites these lists and
        // does not promise an order, so asserting the sequence made this test
        // fail on the ordering of four identical keys.
        $sorted = function (array $config) {
            $types = $config['allowed_item_types'] ?? [];
            sort($types);

            return $types;
        };

        $this->assertSame(
            $sorted($config($this->childId('عيادة'))),
            $sorted($mine),
            'it was given a booking shape the clinic beside it does not use'
        );
    }

    /** «خيارات قابلة للتسعير» — the owner's words, and the whole point. */
    public function test_the_cupping_services_are_priceable(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'خدمات الحجامة')->first();

        $this->assertNotNull($group);
        $this->assertSame('line', (string) $group->price_role, 'a cupping session must be a priced row');

        $names = $this->optionsOf('مركز حجامة', 'خدمات الحجامة');

        foreach (['حجامة رطبة', 'حجامة جافة'] as $expected) {
            $this->assertContains($expected, $names);
        }

        $this->assertSame(
            DB::table('options')->where('group_id', $group->id)->count(),
            count($names),
            'the centre cannot name every session in its own group'
        );
    }

    /**
     * Each of these reaches its trade and holds its role.
     *
     * The three were `modifier` on the argument that a grocer's priced rows are
     * catalog products and not the phrase «حبوب وبقوليات». Two of them —
     * الأجهزة الكهربائية and الأجهزة الرياضية — became lines in the 2026-08-16
     * goods reversal, because ten catalog rows are not a price list for a whole
     * trade. «أصناف المنتجات الغذائية» stayed a modifier and is the survivor of
     * the pattern: it answers «which RANGES do you deal in» for a wholesaler
     * with no market list, which is a narrowing and not a thing bought.
     *
     * The role is read from the authority rather than hard-coded here, so this
     * test stops arguing with `option_price_roles.php` every time it moves.
     *
     * @dataProvider stockGroups
     */
    public function test_a_stock_range_reaches_its_trade(string $groupNameAr, string $childNameAr, string $sample): void
    {
        $group = DB::table('option_groups')->where('name_ar', $groupNameAr)->first();

        $this->assertNotNull($group, "«{$groupNameAr}» was never created");

        $declared = require database_path('seeders/data/option_price_roles.php');
        $authority = in_array($groupNameAr, $declared['line'], true) ? 'line'
            : (in_array($groupNameAr, $declared['modifier'], true) ? 'modifier' : 'descriptive');

        $this->assertSame($authority, (string) $group->price_role,
            "«{$groupNameAr}» disagrees with option_price_roles.php");

        $this->assertContains($sample, $this->optionsOf($childNameAr, $groupNameAr), "«{$childNameAr}» cannot say «{$sample}»");
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function stockGroups(): array
    {
        return [
            // Asked of «مواد غذائية», not «سوبر ماركت», since 2026-08-10. The
            // supermarket prices its aisles off a market list and was being
            // asked the same word twice — once to price it and once to tick it
            // — so the modifier was scoped off it. What survives is the case
            // the group was built for: a wholesaler with no market list, whose
            // only answer to «what do you deal in» is this list.
            // ⚠ …and on 2026-08-24 that group was retired: «زيوت وسمن» was the
            // name of a SHELF and no shelf was ever priced. The wholesaler's
            // answer to «what do you deal in» is now the range itself, which he
            // can also put a price and a quantity on.
            'غذائية' => ['أنواع الزيوت والسمن', 'مواد غذائية', 'زيت ذرة'],
            'كهربائية' => ['أنواع الأجهزة الكهربائية', 'أجهزة كهربائية', 'ثلاجات'],
            'رياضية' => ['أنواع الأجهزة الرياضية', 'أجهزة رياضية', 'مشايات كهربائية'],
        ];
    }

    /**
     * The car-brands rule, which is the pattern the owner named: the trade says
     * the same thing under every root, so the rows are SHARED. Scoping is what
     * once let a shop name 43 brands while the factory beside it named none.
     */
    public function test_a_trade_says_the_same_thing_under_every_root(): void
    {
        $childId = $this->childId('أجهزة كهربائية');

        $this->assertGreaterThan(1, DB::table('category_parent_child')->where('child_id', $childId)->count());

        $scoped = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $childId)
            ->where('g.name_ar', 'أنواع الأجهزة الكهربائية')
            ->where('cco.category_id', '>', 0)
            ->count();

        $this->assertSame(0, $scoped, 'the appliance list is scoped to one root');
    }

    /**
     * A repair workshop can name what it repairs — that is why it is here.
     *
     * The child asked has changed: «تصليح أجهزة كهربائية» became a bench inside
     * «ورشة صيانة أجهزة» on 2026-08-10 and carried the list with it. What is
     * being tested has not changed at all.
     */
    public function test_a_repair_workshop_can_name_what_it_repairs(): void
    {
        $this->assertContains('غسالات ملابس', $this->optionsOf('ورشة صيانة أجهزة', 'أنواع الأجهزة الكهربائية'));
    }

    /** Add-only: a second run writes nothing and withdraws nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        // `category_child_option` is deliberately NOT frozen. The seeder is
        // ADD-ONLY so it never fights the owner's curation, and the moment he
        // unticks an option in the bulk screen an honest re-run puts it back —
        // freezing the count asserts that nobody is using the platform.
        $before = [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_children_master')->count(),
        ];

        $this->artisan('db:seed', ['--class' => 'TradeVocabularySeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_children_master')->count(),
        ]);
    }
}
