<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «قطع غيار سيارات: هناك مصنع يصنع هذه القطع وهناك شركة تستورد او توزع وهناك
 * محل يبيع، فيجب ان يكون لكل واحد منهم قائمة بماركات السيارات … وايضا اذا كانت
 * تخص الميكانيكا او الكهرباء» — owner, 2026-08-09.
 *
 * So the duplicate rows the previous sweep flagged were NOT a mistake — three
 * roots, three businesses, one trade. What was a mistake is that they were
 * three separate child rows with three different vocabularies: the SHOP could
 * name 43 car brands, the FACTORY could name none, and nobody at all could say
 * whether the parts are mechanical or electrical.
 */
class TradeAxesTest extends TestCase
{
    use DatabaseTransactions;

    private const SPARE_PARTS = 44;   // «قطع غيار سيارات»
    private const SPORTS_GEAR = 24;   // «أجهزة رياضية»
    private const SHOPS = 17;
    private const COMPANIES = 22;
    private const FACTORIES = 23;
    private const SHOWROOMS = 21;

    /** One row, three doors — the factory, the distributor and the shop. */
    public function test_the_spare_parts_trade_stands_under_all_three_roots(): void
    {
        $roots = DB::table('category_parent_child')->where('child_id', self::SPARE_PARTS)->pluck('parent_id')
            ->map(fn ($id) => (int) $id)->all();

        foreach ([self::FACTORIES, self::COMPANIES, self::SHOPS] as $root) {
            $this->assertContains($root, $roots, "«قطع غيار سيارات» is missing from root {$root}");
        }
    }

    /** And it says the same things behind every door. */
    public function test_the_factory_can_name_the_same_car_brands_as_the_shop(): void
    {
        $brands = (int) DB::table('option_groups')->where('name_ar', 'ماركات السيارات')->value('id');
        $this->assertGreaterThan(0, $brands);

        $offered = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', self::SPARE_PARTS)
            ->where('o.group_id', $brands)
            ->count();

        $this->assertSame(DB::table('options')->where('group_id', $brands)->count(), $offered);

        // Shared, not scoped to one root: scoping is what let the shop say BMW
        // while the factory next to it could not.
        $this->assertSame(
            0,
            DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->where('cco.child_id', self::SPARE_PARTS)
                ->where('o.group_id', $brands)
                ->where('cco.category_id', '>', 0)
                ->count(),
            'the brands are scoped to a single root'
        );
    }

    /** «اذا كانت تخص الميكانيكا او الكهرباء» — the axis that did not exist. */
    public function test_the_spare_part_domain_axis_exists_and_narrows_rather_than_prices(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'نوع قطع الغيار')->first();

        $this->assertNotNull($group, 'the spare-part domain axis was never created');
        // A priced line since the 2026-08-16 goods reversal: a spares dealer
        // quotes brakes and a clutch at two different prices, and the ten
        // catalog rows behind the trade were never going to be the price list.
        // «درجة قطعة الغيار» (أصلي وكيل / تجاري) is the modifier on top.
        $this->assertSame('line', (string) $group->price_role, 'a spares dealer prices by the part');

        $names = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', self::SPARE_PARTS)
            ->where('o.group_id', $group->id)
            ->pluck('o.name_ar')->all();

        foreach (['ميكانيكا', 'كهرباء'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    /**
     * «ميكانيكا» and «كهرباء» already existed as `line` options meaning an
     * engineering office's priced specialty. Reusing those rows would have
     * priced a brake pad as an engineering service.
     */
    public function test_the_engineering_specialties_were_not_reused(): void
    {
        $domain = (int) DB::table('option_groups')->where('name_ar', 'نوع قطع الغيار')->value('id');
        $engineering = (int) DB::table('option_groups')->where('name_ar', 'تخصصات الهندسة')->value('id');

        if ($engineering <= 0) {
            $this->markTestSkipped('«تخصصات الهندسة» is gone.');
        }

        $this->assertNotSame($domain, $engineering);

        $shared = DB::table('options as a')
            ->join('options as b', 'b.name_ar', '=', 'a.name_ar')
            ->where('a.group_id', $domain)
            ->where('b.group_id', $engineering)
            ->whereColumn('a.id', '=', 'b.id')
            ->count();

        $this->assertSame(0, $shared, 'a spare-part domain and an engineering specialty share a row');
    }

    /** Sports equipment gets the same treatment: factory, company, showroom, shop. */
    public function test_the_sports_equipment_trade_stands_under_its_four_roots(): void
    {
        $roots = DB::table('category_parent_child')->where('child_id', self::SPORTS_GEAR)->pluck('parent_id')
            ->map(fn ($id) => (int) $id)->all();

        foreach ([self::FACTORIES, self::COMPANIES, self::SHOWROOMS, self::SHOPS] as $root) {
            $this->assertContains($root, $roots, "«أجهزة رياضية» is missing from root {$root}");
        }
    }

    /**
     * A new root gets the INTERSECTION of what the trade offers elsewhere, not
     * the union. «معارض» lets you book a viewing; a factory does not, and the
     * first run handed booking to the factory before this rule existed.
     *
     * **«شركات» left this test on 2026-08-11.** One services-bulk save switched
     * `booking` ON for all seventy children of the root at 03:01 — «أجهزة
     * رياضية» among them — and the owner confirmed it the same day: «اذا كنت انا
     * قمت بتعديله فاتركه كما عدلته انا». The intersection rule is about what a
     * SEEDER may infer when a trade arrives at a new root; it was never a veto
     * on the owner enabling a service by hand.
     *
     * The factory half still holds, and it is the half the rule was written
     * for: nobody has said a factory takes appointments, so nothing may infer
     * that it does.
     */
    public function test_a_new_root_does_not_inherit_a_service_only_one_root_had(): void
    {
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $bookingRoots = DB::table('category_platform_services')
            ->where('child_id', self::SPORTS_GEAR)
            ->where('platform_service_id', $booking)
            ->where('is_active', 1)
            ->pluck('category_id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains(self::FACTORIES, $bookingRoots, 'a sports-equipment factory takes bookings');

        // «شركات» is the owner's, as above. What is still asserted is that it
        // is his — an ACTIVE booking link under that root must carry his mark,
        // so a seeder quietly re-creating one is still caught.
        if (in_array(self::COMPANIES, $bookingRoots, true)) {
            $config = DB::table('category_service_configs')
                ->where('category_id', self::COMPANIES)->where('child_id', self::SPORTS_GEAR)
                ->where('platform_service_id', $booking)->value('config');

            $this->assertSame(
                'services_bulk',
                (json_decode((string) $config, true) ?: [])['config_source'] ?? null,
                'booking appeared under شركات from something other than the admin screen'
            );
        }
    }

    /**
     * Both empty twins were detached, and neither master row was deleted —
     * until 2026-08-26, when the owner reviewed the platform's whole
     * rootless list and hard-deleted both himself. One deliberate exception
     * to «لا شىء يُحذف», not a bug for a seeder to reverse.
     */
    public function test_the_empty_twins_are_gone_for_good(): void
    {
        foreach ([43 => 'قطع غيار سيارات', 7 => 'أجهزة رياضية'] as $twinId => $name) {
            $this->assertSame(
                0,
                DB::table('category_children_master')->where('id', $twinId)->count(),
                "«{$name}» #{$twinId} is back — the owner's 2026-08-26 cleanup should not be reversed by a seeder"
            );

            $this->assertSame(0, DB::table('category_parent_child')->where('child_id', $twinId)->count());
            $this->assertSame(0, DB::table('category_platform_services')->where('child_id', $twinId)->count());
            $this->assertSame(0, DB::table('users')->where('category_child_id', $twinId)->count());
        }
    }

    /** Re-running writes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = $this->fingerprint();

        $this->artisan('db:seed', ['--class' => 'TradeAxesSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $this->fingerprint());
    }

    /** The branch review screen buckets every branch and stays read-only. */
    public function test_the_branch_review_screen_groups_the_unreached_ones(): void
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();

        foreach ([AdminAbility::ACCESS, AdminAbility::CATALOG] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        $this->actingAs($admin)
            ->get(route('admin.platform-service-item-groups.review'))
            ->assertOk()
            ->assertSee('لا يصل إلى أي نشاط')
            ->assertSee('معروض ولم يُستخدم بعد')
            ->assertSee('قيد الاستخدام');
    }

    /** @return array<int,int> */
    private function fingerprint(): array
    {
        return [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->whereIn('child_id', [self::SPARE_PARTS, self::SPORTS_GEAR])->count(),
            DB::table('category_parent_child')->whereIn('child_id', [self::SPARE_PARTS, self::SPORTS_GEAR])->count(),
            DB::table('category_platform_services')->whereIn('child_id', [self::SPARE_PARTS, self::SPORTS_GEAR])->count(),
        ];
    }
}
