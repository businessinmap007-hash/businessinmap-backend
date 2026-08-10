<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «اضف الى ورش سيارات وأثاث مثلا وقم بتحويل الابناء الى خيارات كل واحدة منهم حسب
 * الملائمة. مثل كهربائي سيارات وعفشجى وميكاني وهكذا» — owner, 2026-08-10.
 *
 * Twenty-four children, most of them one BENCH in a garage. The bench is now a
 * priced option and the workshop is the child.
 */
class WorkshopRemodelTest extends TestCase
{
    use DatabaseTransactions;

    private function rootId(): int
    {
        return (int) DB::table('categories')->where('slug', 'workshops')->value('id');
    }

    /** The row of that name standing under ورش — «حداد» is two rows. */
    private function childUnderRoot(string $nameAr): int
    {
        return (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $this->rootId())->where('c.name_ar', $nameAr)
            ->value('c.id');
    }

    /** @return array<int,string> */
    private function optionsOf(int $childId, string $groupNameAr): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $childId)->where('g.name_ar', $groupNameAr)
            ->pluck('o.name_ar')->all();
    }

    /**
     * @dataProvider domains
     */
    public function test_each_workshop_stands_with_its_own_priced_bench_list(string $childNameAr, string $groupNameAr, string $sample): void
    {
        $childId = $this->childUnderRoot($childNameAr);

        $this->assertGreaterThan(0, $childId, "«{$childNameAr}» does not stand under ورش");

        $group = DB::table('option_groups')->where('name_ar', $groupNameAr)->first();

        $this->assertNotNull($group, "«{$groupNameAr}» was never created");
        $this->assertSame('line', (string) $group->price_role, 'a booked job must be able to carry a price');

        $this->assertContains($sample, $this->optionsOf($childId, $groupNameAr));
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function domains(): array
    {
        return [
            'سيارات' => ['ورشة سيارات', 'تخصصات ورش السيارات', 'ميكانيكا سيارات'],
            'أثاث' => ['ورشة أثاث ونجارة', 'تخصصات ورش الأثاث', 'تنجيد'],
            'معادن' => ['ورشة حدادة وخراطة', 'تخصصات ورش المعادن', 'خراطة'],
            'أجهزة' => ['ورشة صيانة أجهزة', 'تخصصات ورش الأجهزة', 'تصليح أجهزة كهربائية'],
        ];
    }

    /**
     * The bench stops being a row you hang from. The master row SURVIVES — that
     * is the undo record — but nothing under ورش points at it any more.
     *
     * @dataProvider foldedBenches
     */
    public function test_a_bench_is_no_longer_a_child(string $nameAr): void
    {
        $this->assertSame(0, $this->childUnderRoot($nameAr), "«{$nameAr}» is still a child of ورش");

        $this->assertTrue(
            DB::table('category_children_master')->where('name_ar', $nameAr)->exists(),
            "«{$nameAr}» lost its master row — a remodel here never deletes one"
        );
    }

    /** @return array<string,array{0:string}> */
    public static function foldedBenches(): array
    {
        return [
            'كهربائي سيارات' => ['كهربائي سيارات'],
            'ميكانيكي' => ['ميكانيكي'],
            'سمكري' => ['سمكري'],
            'سروجي' => ['سروجي'],
            'تنجيد' => ['تنجيد'],
            'استورجى' => ['استورجى'],
            'كوتش' => ['كوتش'],
            'مخرطة' => ['مخرطة'],
            'الكريتال' => ['الكريتال'],
        ];
    }

    /**
     * The point of the whole remodel: a merchant that arrives on «ورشة أثاث»
     * with nothing ticked has lost the only thing its old child said about it.
     */
    public function test_a_moved_merchant_still_says_what_it_does(): void
    {
        $childId = $this->childUnderRoot('ورشة أثاث ونجارة');
        $group = (int) DB::table('option_groups')->where('name_ar', 'تخصصات ورش الأثاث')->value('id');

        $ticked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->join('options as o', 'o.id', '=', 'ou.option_id')
            ->where('u.category_child_id', $childId)
            ->where('o.group_id', $group)
            ->where('o.name_ar', 'تنجيد')
            ->count();

        $this->assertGreaterThan(0, $ticked, 'the upholsterers arrived unable to say they upholster');
    }

    /** And everyone who moved sits on the root they moved within. */
    public function test_nobody_was_left_pointing_at_the_wrong_root(): void
    {
        $ids = DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $this->rootId())
            ->where('c.name_ar', 'like', 'ورشة %')
            ->pluck('c.id');

        $stray = DB::table('users')->whereIn('category_child_id', $ids)
            ->where('category_id', '!=', $this->rootId())->count();

        $this->assertSame(0, $stray);
    }

    /**
     * «تنجيد» carried the 43 car marques — an upholsterer who also does car
     * seats — and carrying that verbatim would have made every furniture
     * workshop claim to service BMWs.
     */
    public function test_the_car_marques_stayed_in_the_garage(): void
    {
        $this->assertSame(
            [],
            $this->optionsOf($this->childUnderRoot('ورشة أثاث ونجارة'), 'ماركات السيارات'),
            'the furniture workshop claims to service car brands'
        );

        $this->assertNotSame(
            [],
            $this->optionsOf($this->childUnderRoot('ورشة سيارات'), 'ماركات السيارات'),
            'the garage cannot say which makes it services'
        );
    }

    /** The appliance list survived the fold — WHICH appliance, beside WHICH job. */
    public function test_the_repair_bench_can_still_name_the_appliance(): void
    {
        $this->assertContains(
            'غسالات ملابس',
            $this->optionsOf($this->childUnderRoot('ورشة صيانة أجهزة'), 'أنواع الأجهزة الكهربائية')
        );
    }

    /**
     * Three rows the remodel must not have touched: the tradesman «حداد» #259
     * the owner ruled apart from the workshop, and the two multi-root children
     * that mean something else under another root.
     *
     * @dataProvider untouched
     */
    public function test_what_the_remodel_had_no_business_touching(string $nameAr, string $rootSlug): void
    {
        $rootId = (int) DB::table('categories')->where('slug', $rootSlug)->value('id');

        $this->assertTrue(
            DB::table('category_parent_child as p')
                ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
                ->where('p.parent_id', $rootId)->where('c.name_ar', $nameAr)->exists(),
            "«{$nameAr}» no longer stands under «{$rootSlug}»"
        );
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function untouched(): array
    {
        return [
            'حداد التاجر' => ['حداد', 'professions'],
            'آثاث' => ['آثاث', 'workshops'],
            'تبريد وتكييف' => ['تبريد وتكييف', 'companies'],
            'أويمجى' => ['أويمجى', 'professions'],
        ];
    }

    /** A workshop that cannot be booked is a phone number. */
    public function test_every_new_workshop_can_be_booked(): void
    {
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        foreach (self::domains() as [$childNameAr]) {
            $this->assertTrue(
                DB::table('category_platform_services')
                    ->where('category_id', $this->rootId())
                    ->where('child_id', $this->childUnderRoot($childNameAr))
                    ->where('platform_service_id', $booking)->where('is_active', 1)->exists(),
                "«{$childNameAr}» cannot take a booking"
            );
        }
    }

    /** A second run reports zero of everything. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
            DB::table('category_parent_child')->count(),
            DB::table('category_children_master')->count(),
            DB::table('option_user')->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'WorkshopRemodelSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
