<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Applies the direct-booking classification (see data/booking_child_modes.php)
 * and clears the orphan configs the remodels left behind.
 *
 *   php artisan db:seed --class=BookingChildModesSeeder
 *
 * Merge, never replace: only requires_bookable_item (and, for 'direct',
 * allowed_item_types) are touched; every other config key survives. Idempotent.
 *
 * Orphan sweep: the three-axis remodels detached ~66 children from their roots
 * (medical specialties, property types, sports) but their category_service_configs
 * rows stayed ACTIVE, so they still appeared as bookable children. A config whose
 * (category_id, child_id) pair no longer exists in category_parent_child is
 * deactivated — is_active = 0, never deleted, so re-attaching a child restores it.
 */
class BookingChildModesSeeder extends Seeder
{
    /** The direct-booking price slot: BusinessServicePrice::DEFAULT_ITEM_TYPE. */
    private const DEFAULT_ITEM_TYPE = 'category';

    public function run(): void
    {
        $data = require __DIR__ . '/data/booking_child_modes.php';

        DB::transaction(function () use ($data) {
            $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

            $orphans = $this->deactivateOrphanConfigs($serviceId);
            $counts = ['units' => 0, 'direct' => 0, 'direct_typed' => 0];

            $rows = DB::table('category_service_configs as c')
                ->join('categories as r', 'r.id', '=', 'c.category_id')
                ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
                ->where('c.platform_service_id', $serviceId)
                ->where('c.is_active', 1)
                ->get(['c.id', 'c.config', 'r.slug as root', 'ch.name_ar as child']);

            foreach ($rows as $row) {
                $mode = $data['children'][$row->root][$row->child]
                    ?? $data['defaults'][$row->root]
                    ?? null;

                if (! $mode) {
                    $this->command?->warn("  ! لا تصنيف للجذر «{$row->root}» — تُرك «{$row->child}» كما هو.");
                    continue;
                }

                $counts[$mode]++;

                if ($mode === 'units') {
                    continue; // already the stored behaviour
                }

                $config = json_decode((string) $row->config, true);
                $config = is_array($config) ? $config : [];
                $config['requires_bookable_item'] = false;

                if ($mode === 'direct') {
                    $config['allowed_item_types'] = [self::DEFAULT_ITEM_TYPE];
                }

                DB::table('category_service_configs')->where('id', $row->id)->update([
                    'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }

            $this->command?->info('Booking modes applied:');
            $this->command?->line("  - orphan configs deactivated : {$orphans}");
            $this->command?->line("  - units (instances reserved) : {$counts['units']}");
            $this->command?->line("  - direct_typed (types priced, no units) : {$counts['direct_typed']}");
            $this->command?->line("  - direct (appointment only) : {$counts['direct']}");
        });
    }

    /**
     * A child detached from its root during the remodels still had a live
     * booking config, so it kept showing up as bookable. Deactivate those —
     * both the service link and the config — without deleting either.
     */
    private function deactivateOrphanConfigs(int $serviceId): int
    {
        $orphaned = DB::table('category_service_configs as c')
            ->where('c.platform_service_id', $serviceId)
            ->where('c.is_active', 1)
            ->whereNotExists(function ($q) {
                $q->from('category_parent_child as pc')
                    ->whereColumn('pc.parent_id', 'c.category_id')
                    ->whereColumn('pc.child_id', 'c.child_id');
            })
            ->get(['c.id', 'c.category_id', 'c.child_id']);

        foreach ($orphaned as $row) {
            DB::table('category_service_configs')->where('id', $row->id)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            DB::table('category_platform_services')
                ->where('category_id', $row->category_id)
                ->where('child_id', $row->child_id)
                ->where('platform_service_id', $serviceId)
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }

        return $orphaned->count();
    }
}
