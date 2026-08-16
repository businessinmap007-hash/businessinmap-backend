<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «هناك اكتر من ابن اكسسوار يمكن جمعهم جميعا تحت اكسسوار وتحتها (موبايل -
 * سيارات - كمبيوتر - الخ) من الخيارات» — owner, 2026-08-10.
 *
 * The judgement this pins is the one the instruction did not make: «موبيلات و
 * اكسسوار» sells the PHONE, and its seventeen merchants would have been demoted
 * to an accessory stand by a literal reading. It gets the vocabulary and keeps
 * its row.
 */
class AccessoryMergeTest extends TestCase
{
    use DatabaseTransactions;

    private const GROUP = 'أنواع الإكسسوارات';

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master as c')
            ->join('category_parent_child as p', 'p.child_id', '=', 'c.id')
            ->where('c.name_ar', $nameAr)
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM users u WHERE u.category_child_id = c.id)'))
            ->orderBy('c.id')->value('c.id');
    }

    /** @return array<int,string> */
    private function typesOf(string $childNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->childId($childNameAr))
            ->where('g.name_ar', self::GROUP)->pluck('o.name_ar')->all();
    }

    /**
     * What a shop SELLS, so a line.
     *
     * It was a modifier until 2026-08-16 on the argument that the priced rows
     * are catalog products — and «اكسسوار موبايل» has nineteen of those for the
     * whole platform. The owner reversed the rule for every trade list of this
     * shape; see the goods-reversal block in `option_price_roles.php`.
     */
    public function test_the_accessory_kinds_are_a_priced_line(): void
    {
        $group = DB::table('option_groups')->where('name_ar', self::GROUP)->first();

        $this->assertNotNull($group);
        $this->assertSame('line', (string) $group->price_role);

        foreach (['اكسسوار موبايل', 'اكسسوار سيارات', 'اكسسوار كمبيوتر'] as $expected) {
            $this->assertContains($expected, $this->typesOf('اكسسوار'), "«اكسسوار» cannot say «{$expected}»");
        }
    }

    /** The keeper took the root it needed to receive anyone. */
    public function test_the_trade_reaches_the_shops_root(): void
    {
        $shops = (int) DB::table('categories')->where('slug', 'shops-online')->value('id');
        $childId = $this->childId('اكسسوار');

        $this->assertTrue(
            DB::table('category_parent_child')->where('parent_id', $shops)->where('child_id', $childId)->exists()
        );

        $this->assertGreaterThan(
            0,
            DB::table('category_platform_services')->where('category_id', $shops)
                ->where('child_id', $childId)->where('is_active', 1)->count(),
            'the trade arrived under المحلات offering nothing'
        );
    }

    /**
     * @dataProvider foldedChildren
     */
    public function test_the_folded_child_is_gone_and_its_merchant_can_still_speak(string $nameAr, string $optionAr): void
    {
        $masterId = (int) DB::table('category_children_master')->where('name_ar', $nameAr)->value('id');

        $this->assertGreaterThan(0, $masterId, "«{$nameAr}» lost its master row");
        $this->assertSame(
            0,
            DB::table('category_parent_child')->where('child_id', $masterId)->count(),
            "«{$nameAr}» still stands under a root"
        );
        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $masterId)->count(),
            "a merchant was left on «{$nameAr}»"
        );

        $ticked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->join('options as o', 'o.id', '=', 'ou.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('u.category_child_id', $this->childId('اكسسوار'))
            ->where('g.name_ar', self::GROUP)->where('o.name_ar', $optionAr)
            ->count();

        $this->assertGreaterThan(0, $ticked, "nobody on «اكسسوار» can say «{$optionAr}»");
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function foldedChildren(): array
    {
        return [
            'سيارات' => ['اكسسوارت سيارات', 'اكسسوار سيارات'],
            'موبيلات' => ['اكسسوار موبيلات', 'اكسسوار موبايل'],
        ];
    }

    /** The phone shop keeps its row, its merchants, and gains the words. */
    public function test_the_phone_shop_was_not_folded_away(): void
    {
        $childId = $this->childId('موبيلات و اكسسوار');

        $this->assertGreaterThan(0, $childId, '«موبيلات و اكسسوار» was folded away');

        $this->assertGreaterThanOrEqual(
            17,
            DB::table('users')->where('category_child_id', $childId)->count(),
            'the phone merchants were moved off it'
        );

        /*
         * It used to be asserted that it carries «اكسسوار موبايل» from this
         * group, which was the compensation for not being folded. It has since
         * been given the real thing — «أجهزة الموبايل وملحقاتها», thirteen rows
         * written for it on 2026-08-16 — and this group's four overlapping rows
         * came with ten that do not overlap: حقائب وشنط and مجوهرات on a phone
         * counter. The group is declared empty for it now.
         *
         * What this test is for survives intact: the phone shop kept its row
         * and its merchants, and it can say what it sells.
         */
        $this->assertSame([], $this->typesOf('موبيلات و اكسسوار'), 'the phone shop is offered a handbag again');

        $this->assertNotEmpty(
            app(\App\Services\MerchantOfferingVocabulary::class)->for(0, $childId, 17)['lines'],
            'the phone shop has nothing to price'
        );
    }

    /** Re-running writes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('category_child_option')->count(),
            DB::table('category_parent_child')->count(),
            DB::table('option_user')->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'AccessoryMergeSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
