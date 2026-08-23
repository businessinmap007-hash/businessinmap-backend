<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «انقل مأذون شرعى من مهن وحرفيين الى مكاتب» — owner, 2026-08-09.
 *
 * A filing correction, not a remodel: a مأذون is not a craftsman you call to
 * the house, he receives you at his office beside «محاماه». Nothing is retired
 * and no vocabulary changes — only the root the child hangs from, because that
 * root is where a customer looks.
 *
 * The same conversation set two rules these tests also guard:
 *
 *   «نحن لا نحذف الفروع … ليس معنى ان الفرع لا يوجد حسابات تستخدمه انه يحذف»
 *   «حداد … هو فني حدادة وفى مهن هى ورشة حدادة» — one name under two roots can
 *   be two different trades, so a same-name pair is never merged on sight.
 */
class ChildRootMovesTest extends TestCase
{
    use DatabaseTransactions;

    private const PROFESSIONS = 6;
    private const OFFICES = 19;

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $nameAr)->value('id');
    }

    /** It hangs from مكاتب now, and from مهن no longer. */
    public function test_the_marriage_registrar_moved_to_the_offices_root(): void
    {
        $childId = $this->childId('مأذون شرعى');
        $this->assertGreaterThan(0, $childId);

        $roots = DB::table('category_parent_child')->where('child_id', $childId)
            ->pluck('parent_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains(self::OFFICES, $roots);
        $this->assertNotContains(self::PROFESSIONS, $roots);
    }

    /**
     * A move must carry the wiring, or the child arrives offering nothing —
     * the config rows are keyed on the ROOT, so leaving them behind is silent.
     */
    public function test_its_services_came_with_it(): void
    {
        $childId = $this->childId('مأذون شرعى');
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $this->assertTrue(
            DB::table('category_platform_services')
                ->where('category_id', self::OFFICES)->where('child_id', $childId)
                ->where('platform_service_id', $booking)->where('is_active', 1)->exists(),
            'the booking link stayed behind under مهن وحرفيين'
        );

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('category_id', self::OFFICES)->where('child_id', $childId)
            ->where('platform_service_id', $booking)->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertContains('booking_appointment', $config['allowed_item_types'] ?? []);
        $this->assertFalse((bool) ($config['requires_bookable_item'] ?? false));

        foreach (['category_platform_services', 'category_service_configs', 'category_child_service_fees'] as $table) {
            $this->assertSame(
                0,
                DB::table($table)->where('category_id', self::PROFESSIONS)->where('child_id', $childId)->count(),
                "{$table} still has a row under the old root"
            );
        }
    }

    /** Nobody is left pointing at a root the child no longer hangs from. */
    public function test_no_account_is_left_on_the_old_root(): void
    {
        $childId = $this->childId('مأذون شرعى');

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $childId)->where('category_id', self::PROFESSIONS)->count()
        );
    }

    /**
     * The rest of the pass over the roots. Each of these stood in a root whose
     * own children answer a different question.
     *
     * @dataProvider movedChildren
     */
    public function test_the_misfiled_children_landed_in_the_right_root(string $name, string $fromSlug, string $toSlug): void
    {
        $childId = $this->childId($name);
        $this->assertGreaterThan(0, $childId, "«{$name}» is gone");

        $from = (int) DB::table('categories')->where('slug', $fromSlug)->value('id');
        $to = (int) DB::table('categories')->where('slug', $toSlug)->value('id');

        $roots = DB::table('category_parent_child')->where('child_id', $childId)
            ->pluck('parent_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($to, $roots, "«{$name}» never reached {$toSlug}");
        $this->assertNotContains($from, $roots, "«{$name}» is still under {$fromSlug}");

        // A move that leaves the services behind puts the child in the new root
        // able to sell nothing — the config rows are keyed on the root.
        $this->assertGreaterThan(
            0,
            DB::table('category_platform_services')
                ->where('category_id', $to)->where('child_id', $childId)->where('is_active', 1)->count(),
            "«{$name}» arrived offering nothing"
        );

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $childId)->where('category_id', $from)->count(),
            "an account is still pointing at {$fromSlug}"
        );
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function movedChildren(): array
    {
        return [
            'مأذون شرعى' => ['مأذون شرعى', 'professions', 'offices'],
            // «اصلاح زجاج السيارات» moved professions → workshops here, and on
            // 2026-08-10 the workshop remodel folded it into «ورشة سيارات» as a
            // bench. The move still has to happen first — the remodel only sees
            // children standing under ورش — so the entry stays in the data file
            // and its end state is pinned by test_a_bench_is_no_longer_a_child
            // in WorkshopRemodelTest instead of here.
            'استوديوهات' => ['استوديوهات', 'shops-online', 'arts-entertainment'],
            'مكملات غذائية' => ['مكملات غذائية', 'sports', 'shops-online'],
            // «عفشجى» moved ورش → شحن وتوصيل here, and the owner detached it from
            // شحن وتوصيل on 2026-08-10; its end state is pinned by
            // ChildRootDetachTest instead.
            // «نادي صحي» moved health → sports here, and the owner retired it
            // on 2026-08-14 — «نكتفى ب نادى رياضى واكاديمية». Its end state is
            // pinned by ChildRootDetachTest, like «عفشجى» above.
            'إدارة صفحات' => ['إدارة صفحات', 'technology', 'offices'],
            // «تجهيز عرائس» moved here on 2026-08-09 and was folded onto
            // «كوافير» on 2026-08-10 — it was already one of that child's
            // priced services. ChildRootDetachTest pins where it went.
            'سائق' => ['سائق', 'professions', 'cars'],
        ];
    }

    /**
     * The bridal service kept its three merchants across BOTH steps: the move to
     * مهن وحرفيين and the fold onto the salon that finished it. They sit on it
     * now, each with «تجهيز عرائس» ticked, which is what the child row was
     * saying about them all along.
     *
     * The salon is «متخصص كوافير» #136 since the owner renamed it; it was
     * «كوافير» when this was written, and a lookup by the old name returns
     * nothing, counts nothing, and reports three merchants arriving mute. Held
     * by id — the row is the thing that survived the fold, not the word.
     */
    public function test_the_bridal_service_kept_its_accounts(): void
    {
        $salon = 136;   // «متخصص كوافير», ex-«كوافير»
        $professions = (int) DB::table('categories')->where('slug', 'professions')->value('id');

        $ticked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->join('options as o', 'o.id', '=', 'ou.option_id')
            ->where('u.category_child_id', $salon)
            ->where('o.name_ar', 'تجهيز عرائس')
            ->count();

        $this->assertGreaterThanOrEqual(3, $ticked, 'the bridal merchants arrived unable to say what they do');

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $salon)
                ->where('category_id', '!=', $professions)->count(),
            'an account still points at the old root'
        );
    }

    /**
     * «الصحة» must stay medical facilities only. The health club was the one
     * row in it that a customer looking for a hospital would never want.
     */
    public function test_the_health_root_holds_only_medical_places(): void
    {
        $health = (int) DB::table('categories')->where('slug', 'health')->value('id');

        $names = DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $health)
            ->pluck('c.name_ar')->all();

        $this->assertNotContains('نادي صحي', $names);
        $this->assertContains('عيادة', $names, 'the health root lost a medical child');
    }

    /**
     * «نادي صحي» was moved here so the training service would keep selling
     * through it. On 2026-08-14 the owner retired the child outright —
     * «حذف نادي صحي ونكتفى ب نادى رياضى واكاديمية» — so what has to keep
     * selling training is the child it folded into.
     */
    public function test_the_training_service_survived_the_health_club(): void
    {
        $childId = $this->childId('نادي رياضي');
        $training = (int) DB::table('platform_services')->where('key', 'training')->value('id');

        $this->assertTrue(
            DB::table('category_platform_services')
                ->where('child_id', $childId)
                ->where('platform_service_id', $training)
                ->where('is_active', 1)
                ->exists(),
            'the fold stranded the training service'
        );
    }

    /**
     * A move that changes what the business IS must change what it sells.
     * Carrying the wiring verbatim left «مكملات غذائية» in المحلات still
     * offering booking and training from الرياضة and unable to list one
     * product, and «تجهيز عرائس» in مهن still a shop and unbookable.
     *
     * @dataProvider adoptedShapes
     */
    public function test_a_kind_changing_move_adopts_its_new_roots_services(string $name, string $must, string $mustNot): void
    {
        $childId = $this->childId($name);

        $keys = DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->join('category_parent_child as p', 'p.parent_id', '=', 'l.category_id')
            ->where('l.child_id', $childId)->whereColumn('p.child_id', 'l.child_id')
            ->where('l.is_active', 1)->pluck('s.key')->unique()->all();

        $this->assertContains($must, $keys, "«{$name}» cannot do the thing its new root is for");
        $this->assertNotContains($mustNot, $keys, "«{$name}» kept a service from the root it left");
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function adoptedShapes(): array
    {
        return [
            'مكملات غذائية' => ['مكملات غذائية', 'retail', 'training'],
            // «تجهيز عرائس» folded onto «كوافير» on 2026-08-10.
            // «عفشجى» was the third here until the owner detached it on
            // 2026-08-10 — it stands under no root now, so there is no shape to
            // have adopted.
        ];
    }

    /** A restaurant that could not publish a menu. Thirteen accounts. */
    public function test_a_restaurant_can_publish_a_menu(): void
    {
        $childId = $this->childId('مطعم');
        $root = (int) DB::table('categories')->where('slug', 'restaurants-cafes')->value('id');
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        $this->assertTrue(
            DB::table('category_platform_services')->where('category_id', $root)
                ->where('child_id', $childId)->where('platform_service_id', $menu)
                ->where('is_active', 1)->exists(),
            'the menu link is still switched off'
        );

        $config = json_decode((string) DB::table('category_service_configs')->where('category_id', $root)
            ->where('child_id', $childId)->where('platform_service_id', $menu)
            ->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertContains('menu_food', $config['allowed_item_types'] ?? [], 'the menu allows no food');
    }

    /** Reinstating is a named list, never a heuristic. */
    public function test_the_reinstatement_seeder_is_idempotent(): void
    {
        $before = DB::table('category_platform_services')->where('is_active', 1)->count();

        $this->artisan('db:seed', ['--class' => 'ServiceReinstatementSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, DB::table('category_platform_services')->where('is_active', 1)->count());
    }

    /**
     * «حداد» stays two rows. Under ورش it is the workshop, under مهن it is the
     * tradesman — same word, two businesses — so the earlier "duplicate" reading
     * was wrong and must not be re-applied by a future sweep.
     */
    public function test_the_blacksmith_pair_is_left_alone(): void
    {
        $rows = DB::table('category_children_master')->where('name_ar', 'حداد')->pluck('id');

        $this->assertGreaterThanOrEqual(2, $rows->count(), '«حداد» was merged into one row');

        // The workshop side (#31) became a bench inside «ورشة حدادة وخراطة» on
        // 2026-08-10, so the pair no longer stands under two roots — but the
        // ruling being pinned here was never «two roots», it was «never one
        // row»: the tradesman under مهن وحرفيين must not have been swallowed by
        // the workshop, whichever shape the workshop takes.
        $professions = (int) DB::table('categories')->where('slug', 'professions')->value('id');

        $this->assertTrue(
            DB::table('category_parent_child')->whereIn('child_id', $rows)
                ->where('parent_id', $professions)->exists(),
            'the «حداد» tradesman lost his own row under مهن وحرفيين'
        );

        $this->assertGreaterThan(
            0,
            DB::table('users')->whereIn('category_child_id', $rows)->count(),
            'the blacksmith accounts were moved off the pair'
        );
    }

    /** Re-running the move does nothing at all. */
    public function test_the_seeder_is_idempotent(): void
    {
        $childId = $this->childId('مأذون شرعى');

        $before = [
            DB::table('category_parent_child')->where('child_id', $childId)->count(),
            DB::table('category_platform_services')->where('child_id', $childId)->count(),
            DB::table('category_service_configs')->where('child_id', $childId)->count(),
        ];

        $this->artisan('db:seed', ['--class' => 'ChildRootMovesSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, [
            DB::table('category_parent_child')->where('child_id', $childId)->count(),
            DB::table('category_platform_services')->where('child_id', $childId)->count(),
            DB::table('category_service_configs')->where('child_id', $childId)->count(),
        ]);
    }
}
