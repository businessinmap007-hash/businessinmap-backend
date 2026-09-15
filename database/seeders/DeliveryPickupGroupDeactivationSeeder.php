<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «التسليم والاستلام» (option_groups #49) is retired by deactivating the
 * GROUP alone - never by deleting its options, links, or
 * category_child_option_decisions. An earlier attempt to delete it outright
 * (2026-09-15) turned out to break `child_option_groups.php`'s "fulfilment"
 * bundle for ~300 children platform-wide (see git history / that day's
 * incident report) - the schema's only real per-option retirement boundary
 * is the group's own is_active flag (see RetireDuplicateOptionsSeeder's own
 * doc comment), so this is the one safe, reversible way to hide it.
 *
 * CategoryChild::activeOptions() is what actually enforces this (both the
 * business's own attributes screen and the customer discovery filter read
 * that relation) - it did not check option_groups.is_active at all before
 * 2026-09-15, so deactivating a group alone never used to have any visible
 * effect anywhere.
 *
 * Replaced by BusinessMenuSetting::labelsFor() + supports_delivery/
 * supports_pickup/supports_international_shipping/supports_domestic_shipping,
 * decided by what the business actually sells, not its category.
 */
class DeliveryPickupGroupDeactivationSeeder extends Seeder
{
    private const GROUP_NAME_AR = 'التسليم والاستلام';

    public function run(): void
    {
        $updated = DB::table('option_groups')
            ->where('name_ar', self::GROUP_NAME_AR)
            ->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $this->command?->info("Delivery & Pickup group deactivated (rows updated: {$updated}).");
    }
}
