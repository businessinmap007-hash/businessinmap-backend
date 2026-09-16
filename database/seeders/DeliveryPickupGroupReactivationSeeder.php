<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reverses DeliveryPickupGroupDeactivationSeeder (2026-09-16, same day):
 * the freight-label auto-detection built to replace this group
 * (BusinessMenuSetting::labelsFor(), keyed on whether a business carries
 * `retail`) turned out unreliable - retail and menu coexist on ~56 goods
 * children ON PURPOSE (a produce shop selling both at the counter and in
 * bulk), so an ordinary greengrocer with retail enabled got mislabeled as a
 * factory shipping pallets.
 *
 * Reactivated instead: the merchant says directly, on the same options
 * screen every other option group already uses, which of these it offers.
 * Checkout reads that selection - see BusinessPageController::show() and
 * the mobile checkout/fulfillment-selector screens.
 *
 * Also adds «استلام من المكان» (Pickup From Location) as a sixth option,
 * granted to every child that already carries at least one of the other
 * five - the generic "pickup, no shipping" answer next to the specific
 * ones (factory-gate pickup, free delivery, ...).
 */
class DeliveryPickupGroupReactivationSeeder extends Seeder
{
    private const GROUP_NAME_AR = 'التسليم والاستلام';
    private const NEW_OPTION_AR = 'استلام من المكان';
    private const NEW_OPTION_EN = 'Pickup From Location';

    public function run(): void
    {
        $groupId = DB::table('option_groups')->where('name_ar', self::GROUP_NAME_AR)->value('id');

        if (! $groupId) {
            $this->command?->warn(self::GROUP_NAME_AR . ' group not found.');

            return;
        }

        $updated = DB::table('option_groups')
            ->where('id', $groupId)
            ->where('is_active', 0)
            ->update(['is_active' => 1, 'updated_at' => now()]);

        $optionId = (int) DB::table('options')
            ->where('group_id', $groupId)->where('name_ar', self::NEW_OPTION_AR)->value('id');

        $created = false;

        if ($optionId <= 0) {
            $optionId = (int) DB::table('options')->insertGetId([
                'group_id' => $groupId,
                'name_ar' => self::NEW_OPTION_AR,
                'name_en' => self::NEW_OPTION_EN,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created = true;
        }

        $siblingIds = DB::table('options')->where('group_id', $groupId)->where('id', '!=', $optionId)->pluck('id');

        $children = DB::table('category_child_option')
            ->whereIn('option_id', $siblingIds)
            ->select('child_id', 'category_id')
            ->distinct()
            ->get();

        $granted = 0;

        foreach ($children as $row) {
            $exists = DB::table('category_child_option')
                ->where('child_id', $row->child_id)
                ->where('category_id', $row->category_id)
                ->where('option_id', $optionId)
                ->exists();

            if (! $exists) {
                DB::table('category_child_option')->insert([
                    'child_id' => $row->child_id,
                    'category_id' => $row->category_id,
                    'option_id' => $optionId,
                    'reorder' => 0,
                ]);
                $granted++;
            }
        }

        $this->command?->info('Delivery & Pickup group reactivated (rows updated: '.$updated.').');
        $this->command?->line('  - '.self::NEW_OPTION_AR.' option created : '.($created ? 'yes' : 'already existed'));
        $this->command?->line('  - links granted for the new option : '.$granted);
    }
}
