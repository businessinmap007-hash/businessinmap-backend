<?php

namespace Tests\Feature;

use Database\Seeders\OrphanChildLinksCleanupSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «هل هناك خيارات وخدمات ليس لها استخدام او غير مروبطه بأبناء لنقوم بحذفها» —
 * owner, 2026-08-08.
 *
 * The health, sports and property remodels turned dozens of CHILDREN into
 * OPTIONS. Each was detached from its root — that is what makes it unreachable —
 * but its service wiring stayed: 170 links, 97 configs and 24 FEE rows for
 * children nobody can pick, browse or filter. The five hotel star ratings were
 * still being given 10 EGP booking fees in July, months after nothing could
 * reach them.
 *
 * The master child rows themselves are NOT swept, and deliberately so: they are
 * the undo record for those remodels (HealthRemodelSeeder is non-destructive by
 * design and restores a specialty by re-inserting one pivot row).
 */
class OrphanChildLinksTest extends TestCase
{
    use DatabaseTransactions;

    /** @return \Illuminate\Support\Collection<int,int> */
    private function unreachable()
    {
        return DB::table('category_children_master as c')
            ->leftJoin('category_parent_child as pc', 'pc.child_id', '=', 'c.id')
            ->whereNull('pc.child_id')
            ->pluck('c.id');
    }

    /** No service may be wired to a child no one can reach. */
    public function test_an_unreachable_child_carries_no_service_wiring(): void
    {
        $ids = $this->unreachable();

        if ($ids->isEmpty()) {
            $this->markTestSkipped('Every child is attached to a root.');
        }

        foreach ([
            'category_platform_services' => 'روابط',
            'category_child_service_fees' => 'رسوم',
        ] as $table => $label) {
            $stray = DB::table($table)->whereIn('child_id', $ids)->pluck('child_id')->unique();

            $this->assertEmpty(
                $stray->all(),
                "{$table}: {$label} still wired to unreachable children " . $stray->implode('، ')
            );
        }
    }

    /**
     * The master rows stay. Sweeping them would make the remodels one-way, and
     * every one of them promises the opposite in its own docblock.
     */
    public function test_the_sweep_leaves_the_child_rows_alone(): void
    {
        $before = DB::table('category_children_master')->count();

        (new OrphanChildLinksCleanupSeeder)->run();

        $this->assertSame(
            $before,
            DB::table('category_children_master')->count(),
            'the undo record for the health/sports/property remodels was deleted'
        );
    }

    /** An unreachable child that is still USED is a tree bug, not debris. */
    public function test_a_child_still_in_use_is_never_swept(): void
    {
        $ids = $this->unreachable();

        if ($ids->isEmpty()) {
            $this->markTestSkipped('Every child is attached to a root.');
        }

        $this->assertSame(0, DB::table('users')->whereIn('category_child_id', $ids)->count(),
            'a business sits on a child that no root can reach');
        $this->assertSame(0, DB::table('business_service_prices')->whereIn('child_id', $ids)->count(),
            'a priced row points at a child that no root can reach');
    }

    /**
     * An option reaching no child is NOT automatically debris.
     *
     * «وحدة معروضة» and «قطعة أثاث» reach nobody and never will while
     * «عقارات وممتلكات» and «أثاث وتشطيب منزلي» exist — MenuLineOptionsSeeder
     * refuses to trade a richer vocabulary down for a poorer one. They are
     * declared bands and the fallback for a future child with no line group.
     * Deleting them only made the seeder put them back on the next run, which
     * is how this test exists at all.
     */
    public function test_a_declared_band_survives_the_sweep(): void
    {
        $declared = require database_path('seeders/data/menu_line_bands.php');

        $names = array_keys($declared['bands'] ?? []);

        if ($names === []) {
            $this->markTestSkipped('No menu bands are declared.');
        }

        (new OrphanChildLinksCleanupSeeder)->run();

        // The bands live in four groups since 2026-08-10 (MenuBandSplitSeeder) —
        // «بنود المنيو» kept the fourteen restaurant headings and the aisles,
        // farm supplies and listing rows moved out. Still one declaration, still
        // one seeder; only the drawer changed.
        $family = array_merge(
            ['بنود المنيو'],
            array_column((require database_path('seeders/data/menu_band_split.php'))['groups'], 'name_ar')
        );

        $missing = array_values(array_diff($names, DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', $family)
            ->pluck('o.name_ar')
            ->all()));

        $this->assertSame([], $missing, 'the sweep removed a band a seeder declares: ' . implode('، ', $missing));
    }

    /** Re-running writes nothing. */
    public function test_the_sweep_is_idempotent(): void
    {
        (new OrphanChildLinksCleanupSeeder)->run();

        $before = [
            DB::table('options')->count(),
            DB::table('category_platform_services')->count(),
            DB::table('category_service_configs')->count(),
            DB::table('category_child_service_fees')->count(),
        ];

        (new OrphanChildLinksCleanupSeeder)->run();

        $this->assertSame($before, [
            DB::table('options')->count(),
            DB::table('category_platform_services')->count(),
            DB::table('category_service_configs')->count(),
            DB::table('category_child_service_fees')->count(),
        ]);
    }
}
