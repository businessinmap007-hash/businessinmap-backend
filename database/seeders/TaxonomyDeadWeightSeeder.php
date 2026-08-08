<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sweeps what the taxonomy remodels left behind.
 *
 *     php artisan db:seed --class=TaxonomyDeadWeightSeeder
 *
 * Two kinds of debris, both invisible until something goes looking:
 *
 * **1. Options nothing can ever reach.** An option linked to no child cannot be
 * ticked, priced, filtered or scoped — it exists only to lengthen a list in the
 * admin panel. Only ones with no trace anywhere are dropped: not on a child,
 * not ticked by a merchant, not on a priced row, not naming a bookable unit.
 *
 * **2. Service wiring for children nobody can reach.** The health, sports and
 * property remodels turned dozens of CHILDREN into OPTIONS — أسنان and عيون
 * became specialties a hospital ticks, تنس and سباحة became sports a venue
 * hosts, شقة and ڤيلا became property types. Each was detached from its root,
 * which is what makes it unreachable, but its `category_platform_services`,
 * `category_service_configs` and `category_child_service_fees` rows stayed.
 *
 * Those rows are not harmless. Every audit over service wiring has to carry
 * them, the fee screens still price them — the five hotel star ratings were
 * still being given 10 EGP booking fees in July, months after nothing could
 * reach them — and any future join over a child id meets rows with no parent.
 *
 * **Reachability is the test, not age.** A child attached to no root in
 * `category_parent_child` cannot be picked at registration, cannot be browsed,
 * cannot be filtered. Anything still holding a business, an option link or a
 * priced row is left exactly where it is, whatever the tree says.
 *
 * Idempotent: on a clean database it reports zeroes.
 */
class TaxonomyDeadWeightSeeder extends Seeder
{
    /**
     * Options to drop, by (group name, option name).
     *
     * Named rather than taken by id so the file says WHAT is being removed, and
     * so a database where they were already cleaned simply matches nothing.
     *
     * **Empty, and the reason is worth keeping.** The two that looked orphaned —
     * «وحدة معروضة» and «قطعة أثاث» — reach no child and never will while
     * «عقارات وممتلكات» and «أثاث وتشطيب منزلي» exist, because
     * MenuLineOptionsSeeder refuses to trade a richer vocabulary down for a
     * poorer one. They are declared bands in `data/menu_line_bands.php` and the
     * FALLBACK for a future child that has no line group at all. Unlinked is not
     * unused. Deleting them only made the seeder recreate them on the next run.
     *
     * Before adding anything here, grep the seeders for its name.
     *
     * @var array<int,array{0:string,1:string}>
     */
    private const ORPHAN_OPTIONS = [];

    public function run(): void
    {
        DB::transaction(function () {
            $options = $this->dropOrphanOptions();
            [$links, $configs, $fees, $children] = $this->dropUnreachableWiring();

            $this->command?->info('Taxonomy dead weight:');
            $this->command?->line("  - خيارات يتيمة حُذفت : {$options}");
            $this->command?->line("  - أبناء لا يصلهم جذر : {$children}");
            $this->command?->line("  - روابط خدمات معلّقة : {$links}");
            $this->command?->line("  - إعدادات خدمات معلّقة : {$configs}");
            $this->command?->line("  - رسوم خدمات معلّقة : {$fees}");
        });
    }

    /** An option with no trace anywhere. Anything held back stays. */
    private function dropOrphanOptions(): int
    {
        $dropped = 0;

        foreach (self::ORPHAN_OPTIONS as [$group, $name]) {
            $id = (int) DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', $group)
                ->where('o.name_ar', $name)
                ->value('o.id');

            if ($id <= 0) {
                continue;
            }

            $held = DB::table('category_child_option')->where('option_id', $id)->exists()
                || DB::table('option_user')->where('option_id', $id)->exists()
                || DB::table('offering_options')->where('option_id', $id)->exists()
                || DB::table('business_service_prices')->where('line_option_id', $id)->exists()
                || DB::table('bookable_items')->where('line_option_id', $id)->exists();

            if ($held) {
                $this->command?->warn("  ! «{$name}» صار مستخدَمًا — تُرك.");

                continue;
            }

            $dropped += DB::table('options')->where('id', $id)->delete();
        }

        return $dropped;
    }

    /**
     * @return array{0:int,1:int,2:int,3:int} links, configs, fees, children
     */
    private function dropUnreachableWiring(): array
    {
        $unreachable = DB::table('category_children_master as c')
            ->leftJoin('category_parent_child as pc', 'pc.child_id', '=', 'c.id')
            ->whereNull('pc.child_id')
            ->pluck('c.id')
            ->map(fn ($id) => (int) $id);

        if ($unreachable->isEmpty()) {
            return [0, 0, 0, 0];
        }

        // Unreachable is not the same as unused. A child still holding a
        // business, an option or a price is a tree problem to fix, not debris
        // to sweep — leave it and let ServiceWiringIntegrityTest keep asking.
        $inUse = collect()
            ->merge(DB::table('users')->whereIn('category_child_id', $unreachable)->pluck('category_child_id'))
            ->merge(DB::table('category_child_option')->whereIn('child_id', $unreachable)->pluck('child_id'))
            ->merge(DB::table('business_service_prices')->whereIn('child_id', $unreachable)->pluck('child_id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $sweep = $unreachable->diff($inUse)->values();

        if ($sweep->isEmpty()) {
            return [0, 0, 0, $unreachable->count()];
        }

        $links = DB::table('category_platform_services')->whereIn('child_id', $sweep)->delete();
        $configs = DB::table('category_service_configs')->whereIn('child_id', $sweep)->delete();
        $fees = DB::table('category_child_service_fees')->whereIn('child_id', $sweep)->delete();

        return [$links, $configs, $fees, $sweep->count()];
    }
}
