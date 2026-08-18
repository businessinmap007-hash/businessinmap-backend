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

    /**
     * DISTINCT names. A link may exist once as a shared row and again per root
     * — the admin's bulk-options screen splits a shared row the moment anyone
     * saves one root's list — so counting ROWS measures storage, not vocabulary.
     *
     * @return array<int,string>
     */
    private function typesOf(string $childNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($childNameAr))
            ->where('g.name_ar', self::GROUP)
            ->distinct()->pluck('o.name_ar')->all();
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
     * The car-brands rule: one trade says the same thing whatever door it stands
     * behind. Scoping is what once let a shop name 43 brands while the factory
     * beside it named none.
     *
     * Asserted as REACH, not as storage. The rows may be shared (category_id 0)
     * or split per root — the bulk-options screen splits a shared row the moment
     * an admin saves one root's list, and that is legitimate. What must hold is
     * that every root the trade stands under can still say all sixteen.
     */
    public function test_the_trade_says_the_same_thing_under_every_root(): void
    {
        $childId = $this->childId('باب وشباك');

        $all = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::GROUP)->count();

        // The three roots the trade SELLS from. «مهن وحرفيين» is left out on
        // purpose: it holds no account there and its list is the admin's to
        // curate in the bulk screen — the rule this guards is «the same words
        // wherever the trade is sold», not «every standing must be identical».
        $roots = DB::table('categories')->whereIn('slug', ['factories', 'companies', 'shops-online'])
            ->pluck('id');

        foreach ($roots as $rootId) {
            $reach = DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('cco.child_id', $childId)->where('g.name_ar', self::GROUP)
                ->whereIn('cco.category_id', [0, (int) $rootId])
                ->distinct()->count('o.id');

            $slug = DB::table('categories')->where('id', $rootId)->value('slug');

            $this->assertSame($all, $reach, "«باب وشباك» cannot say every door type under «{$slug}»");
        }
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
            'the trade is back under ورش beside «ورشة باب وشباك»'
        );

        $this->assertTrue(
            DB::table('category_parent_child as p')
                ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
                ->where('p.parent_id', $this->rootId('workshops'))->where('c.name_ar', 'ورشة باب وشباك')->exists(),
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
     * «ادمج pvc وباب وشباك فهما نفس الخيارات ونفس الهدف» — owner, 2026-08-12.
     *
     * Both product children have now folded into the trade: «أبواب مصفحة» #23
     * on 2026-08-10, «بي في سي» #289 on 2026-08-12. UPVC is a MATERIAL, and it
     * stands as one of the sixteen types below.
     *
     * The fold is only honest if the three merchants kept what the child was
     * saying about them, so this checks the tick as well as the move. Arriving
     * mute is a demotion dressed as a merge.
     */
    public function test_the_upvc_child_folded_into_the_trade(): void
    {
        $upvc = (int) DB::table('category_children_master')->where('id', 289)->value('id');

        $this->assertSame(289, $upvc, 'nothing here deletes a master row');
        $this->assertSame(0, DB::table('category_parent_child')->where('child_id', 289)->count());
        $this->assertSame(0, DB::table('category_child_option')->where('child_id', 289)->count());
        $this->assertSame(0, DB::table('users')->where('category_child_id', 289)->count());

        $option = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::GROUP)->where('o.name_ar', 'بي في سي (UPVC)')->value('o.id');

        $arrived = DB::table('users')->where('category_child_id', $this->childId('باب وشباك'))
            ->where('category_id', $this->rootId('factories'))->pluck('id');

        $this->assertGreaterThanOrEqual(3, $arrived->count(), 'the three UPVC merchants landed here');

        foreach ($arrived as $userId) {
            $this->assertTrue(
                DB::table('option_user')->where('user_id', $userId)->where('option_id', $option)->exists(),
                "u{$userId} arrived without the word its child was saying"
            );
        }
    }

    /**
     * «المونتال … لبيع قطاعات الالمونتال نفسها وليس الشباك والباب» — owner,
     * 2026-08-12.
     *
     * «ألمونتال» #17 had been given seven of the sixteen door types because the
     * two trades stand beside each other. They are one step apart, not one
     * trade: this one sells the EXTRUSION to the workshop that then makes the
     * window. So it has its own list, and the door list is a declared empty in
     * `child_option_scopes.php`.
     *
     * It also took مصانع that day — somebody presses the profile before anybody
     * wholesales it — and the standing is only real if it can sell there.
     */
    public function test_the_extrusion_trade_is_not_the_window_trade(): void
    {
        $upvc = $this->childId('ألمونتال');

        $this->assertSame([], $this->typesOf('ألمونتال'), 'the extrusion trade kept the door list');

        $profiles = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $upvc)
            ->where('g.name_ar', 'قطاعات ومنتجات الألومنيوم')
            ->pluck('o.name_ar');

        $this->assertContains('قطاعات ثرمال بريك', $profiles);

        // Shared, so the shop, the showroom, the wholesaler and now the factory
        // all name the same profiles — the «ماركات السيارات» rule.
        $this->assertSame([0], DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $upvc)->where('g.name_ar', 'قطاعات ومنتجات الألومنيوم')
            ->distinct()->pluck('co.category_id')->map(fn ($id) => (int) $id)->all());

        $factories = $this->rootId('factories');

        $this->assertTrue(
            DB::table('category_parent_child')->where('parent_id', $factories)
                ->where('child_id', $upvc)->exists(),
            'ألمونتال did not take مصانع'
        );

        $sells = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.child_id', $upvc)->where('c.category_id', $factories)
            ->where('c.is_active', 1)->pluck('s.key')->all();

        $this->assertContains('retail', $sells, 'a standing that can sell nothing is not a standing');
    }

    /**
     * «انقلهما الى باب وشباك» — owner, 2026-08-12.
     *
     * Two accounts read as the window trade in their own names — «معرض
     * الوميتال مطابخ و شبابيك و ابواب» and «مطابخ الوميتال وحشب حديثه» — and
     * were filed under «ألمونتال», which sells the extrusion to whoever makes
     * the opening. The trade had to take معارض first: there was nowhere under
     * their own root to move them to, and an account pointing at a child that
     * does not hang from its root disappears from every screen.
     *
     * Each keeps «ألومنيوم» — the word its old child was saying about it.
     */
    public function test_the_showroom_accounts_moved_to_the_window_trade(): void
    {
        $doors = $this->childId('باب وشباك');
        $showrooms = $this->rootId('exhibitions');

        $this->assertTrue(
            DB::table('category_parent_child')->where('parent_id', $showrooms)
                ->where('child_id', $doors)->exists(),
            'the trade cannot hold a showroom account from outside معارض'
        );

        $this->assertContains('ألومنيوم', $this->typesOf('باب وشباك'));

        $aluminium = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::GROUP)->where('o.name_ar', 'ألومنيوم')->value('o.id');

        foreach ([1519, 1313] as $userId) {
            $user = DB::table('users')->where('id', $userId)->first(['category_id', 'category_child_id']);

            if ($user === null) {
                continue; // the account is the owner's to keep or close
            }

            $this->assertSame($doors, (int) $user->category_child_id, "u{$userId} did not move");

            $this->assertTrue(
                DB::table('category_parent_child')->where('parent_id', (int) $user->category_id)
                    ->where('child_id', $doors)->exists(),
                "u{$userId} points at a child its root does not carry"
            );

            $this->assertTrue(
                DB::table('option_user')->where('user_id', $userId)->where('option_id', $aluminium)->exists(),
                "u{$userId} arrived without the word its child was saying"
            );
        }
    }

    /**
     * The workshop that makes them says the same thing the factory says.
     *
     * Sorted, because the two lists are the same SET and the order is whatever
     * each child's rows were written in — the two kitchen rows added on
     * 2026-08-12 landed first on one and last on the other, which says nothing
     * about what either of them makes.
     */
    public function test_the_workshop_carries_the_same_list(): void
    {
        $trade = $this->typesOf('باب وشباك');
        $workshop = $this->typesOf('ورشة باب وشباك');
        sort($trade);
        sort($workshop);

        $this->assertSame($trade, $workshop, 'the workshop and the trade disagree about what a door is');
    }

    /** Re-running either seeder writes nothing and withdraws nothing. */
    public function test_the_seeders_are_idempotent(): void
    {
        // `category_child_option` is deliberately NOT frozen here. Both seeders
        // are ADD-ONLY so they never fight the owner's curation — which means
        // that the moment he unticks something in the bulk-options screen, an
        // honest re-run puts it back and the count legitimately moves. Freezing
        // it asserts that nobody is using the platform.
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_parent_child')->count(),
            DB::table('category_platform_services')->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'TradeVocabularySeeder', '--no-interaction' => true])->run();
        $this->artisan('db:seed', ['--class' => 'TradeAxesSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
