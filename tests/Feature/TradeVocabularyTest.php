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
     * The other three narrow, they do not price: the priced rows for a grocer
     * are the products in the catalog, not the phrase «حبوب وبقوليات».
     *
     * @dataProvider stockGroups
     */
    public function test_a_stock_range_narrows_rather_than_prices(string $groupNameAr, string $childNameAr, string $sample): void
    {
        $group = DB::table('option_groups')->where('name_ar', $groupNameAr)->first();

        $this->assertNotNull($group, "«{$groupNameAr}» was never created");
        $this->assertSame('modifier', (string) $group->price_role, "«{$groupNameAr}» must narrow, not price");

        $this->assertContains($sample, $this->optionsOf($childNameAr, $groupNameAr), "«{$childNameAr}» cannot say «{$sample}»");
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function stockGroups(): array
    {
        return [
            'غذائية' => ['أصناف المنتجات الغذائية', 'سوبر ماركت', 'زيوت وسمن'],
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

    /** A repair workshop can name what it repairs — that is why it is here. */
    public function test_a_repair_workshop_can_name_what_it_repairs(): void
    {
        $this->assertContains('غسالات ملابس', $this->optionsOf('تصليح أجهزة كهربائية', 'أنواع الأجهزة الكهربائية'));
    }

    /** Add-only: a second run writes nothing and withdraws nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
            DB::table('category_children_master')->count(),
        ];

        $this->artisan('db:seed', ['--class' => 'TradeVocabularySeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
            DB::table('category_children_master')->count(),
        ]);
    }
}
