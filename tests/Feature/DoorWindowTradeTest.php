<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «اريد اضافة فى باب وشباك الابواب المصفحة والشاتر واذا كان هناك انواع اخرى تضاف
 * فى مجموعة خيارات جديدة تتماشى مع upvc وابواب وشباك سواء مصانع او شركات او محلات
 * او ورش» — owner, 2026-08-10.
 *
 * Two halves. The vocabulary: sixteen door and window types in one `line` group,
 * shared by every child of the trade. And the standings: the trade stood under
 * three of the four roots he named, and under شركات what stood instead was a
 * single PRODUCT — «أبواب مصفحة» — which is now one of the sixteen types.
 */
class DoorWindowTradeTest extends TestCase
{
    use DatabaseTransactions;

    private const GROUP = 'أنواع الأبواب والشبابيك';

    /**
     * The child of that name a customer can REACH — joined to a root, tie-broken
     * by accounts. `where(name_ar)->value('id')` returns the lowest id, which for
     * several names is the retired twin.
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

    private function rootId(string $slug): int
    {
        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    /** @return array<int,string> */
    private function typesOf(string $childNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($childNameAr))
            ->where('g.name_ar', self::GROUP)
            ->pluck('o.name_ar')->all();
    }

    /**
     * `line`, not modifier — the point of the whole slice. There is no catalog of
     * door models behind this list, so the type IS the priced row: a workshop
     * quotes «شاتر كهربائي» by the metre exactly as the joinery beside it quotes
     * «غرفة نوم» out of `أثاث وتشطيب منزلي`.
     */
    public function test_a_door_type_is_a_priced_row(): void
    {
        $group = DB::table('option_groups')->where('name_ar', self::GROUP)->first();

        $this->assertNotNull($group, 'the door/window group was never created');
        $this->assertSame('line', (string) $group->price_role);
        $this->assertSame(1, (int) $group->is_active);
    }

    /** The two the owner named by hand, and the one he named as the reference. */
    public function test_the_trade_can_say_armoured_doors_and_shutters(): void
    {
        $types = $this->typesOf('باب وشباك');

        foreach (['أبواب مصفحة', 'شاتر يدوي', 'شاتر كهربائي', 'بي في سي (UPVC)'] as $expected) {
            $this->assertContains($expected, $types, "«باب وشباك» cannot say «{$expected}»");
        }
    }

    /**
     * The car-brands rule: one trade, one vocabulary, whatever door it stands
     * behind. A root-scoped row is what once let a shop name 43 brands while the
     * factory beside it named none.
     */
    public function test_the_types_are_shared_not_scoped_to_one_root(): void
    {
        $scoped = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId('باب وشباك'))
            ->where('g.name_ar', self::GROUP)
            ->where('cco.category_id', '>', 0)
            ->count();

        $this->assertSame(0, $scoped, 'the door types are scoped to one root');
    }

    /**
     * «سواء مصانع او شركات او محلات او ورش».
     *
     * Three of the four are the trade itself. The fourth is served by a
     * different row and always was: the owner detached «باب وشباك» from ورش the
     * same day — «حذف … باب وشباك من ابناء الورش» — because «نجار باب وشباك» #84
     * is the workshop, holds the workshop accounts, and carries the same sixteen
     * types. So all four standings are answered; only one of them is answered by
     * another child.
     */
    public function test_the_trade_stands_under_every_root_it_sells_from(): void
    {
        $childId = $this->childId('باب وشباك');

        foreach (['factories', 'companies', 'shops-online'] as $slug) {
            $this->assertTrue(
                DB::table('category_parent_child')
                    ->where('parent_id', $this->rootId($slug))->where('child_id', $childId)->exists(),
                "«باب وشباك» does not stand under «{$slug}»"
            );
        }

        $this->assertFalse(
            DB::table('category_parent_child')
                ->where('parent_id', $this->rootId('workshops'))->where('child_id', $childId)->exists(),
            'the trade is back under ورش beside «نجار باب وشباك»'
        );

        $this->assertTrue(
            DB::table('category_parent_child as p')
                ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
                ->where('p.parent_id', $this->rootId('workshops'))->where('c.name_ar', 'نجار باب وشباك')->exists(),
            'ورش has no doors workshop at all'
        );
    }

    /**
     * Standing under a root is worthless if nothing can be sold there. This is
     * the failure the intersection fallback would have produced on its own:
     * delivery + offers and no selling surface at all.
     *
     * @dataProvider sellingRoots
     */
    public function test_a_doors_company_or_shop_can_actually_sell(string $slug): void
    {
        $rootId = $this->rootId($slug);
        $childId = $this->childId('باب وشباك');
        $retail = (int) DB::table('platform_services')->where('key', 'retail')->value('id');

        $this->assertTrue(
            DB::table('category_platform_services')->where('category_id', $rootId)
                ->where('child_id', $childId)->where('platform_service_id', $retail)
                ->where('is_active', 1)->exists(),
            "«باب وشباك» has no selling surface under «{$slug}»"
        );

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $childId)
            ->where('platform_service_id', $retail)->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertNotSame([], $config['allowed_item_types'] ?? [], "«{$slug}» hands the merchant an unbounded picker");
    }

    /** @return array<string,array{0:string}> */
    public static function sellingRoots(): array
    {
        return ['شركات' => ['companies'], 'المحلات' => ['shops-online']];
    }

    /**
     * Nothing was retired. «أبواب مصفحة» and «بي في سي» are products filed as
     * trades, and whether they fold into «باب وشباك» is the owner's call — so
     * both keep their rows, their accounts, and now say the same sixteen words.
     *
     * @dataProvider productChildren
     */
    public function test_the_product_children_are_left_standing_and_given_the_words(string $nameAr, string $rootSlug): void
    {
        $childId = $this->childId($nameAr);

        $this->assertTrue(
            DB::table('category_parent_child')
                ->where('parent_id', $this->rootId($rootSlug))->where('child_id', $childId)->exists(),
            "«{$nameAr}» was detached — nothing here may retire a child"
        );

        $this->assertCount(16, $this->typesOf($nameAr));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function productChildren(): array
    {
        return [
            'أبواب مصفحة' => ['أبواب مصفحة', 'companies'],
            'بي في سي' => ['بي في سي', 'factories'],
        ];
    }

    /** The workshop that makes them says the same thing the factory says. */
    public function test_the_workshop_carries_the_same_list(): void
    {
        $this->assertSame(
            $this->typesOf('باب وشباك'),
            $this->typesOf('نجار باب وشباك'),
            'the workshop and the trade disagree about what a door is'
        );
    }

    /** Re-running either seeder writes nothing and withdraws nothing. */
    public function test_the_seeders_are_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
            DB::table('category_parent_child')->count(),
            DB::table('category_platform_services')->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'TradeVocabularySeeder', '--no-interaction' => true])->run();
        $this->artisan('db:seed', ['--class' => 'TradeAxesSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
