<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A sixth `menu` kind for a service company's own catalogue - "خدمات"
 * (Services): an ad agency names a campaign package, a software house names
 * a language or a machine-control job, the way a restaurant names a dish.
 * Same generic MenuSection/MenuItem mechanism every other kind already uses
 * - the section names the offering, the items under it are its branches.
 *
 * Written by hand, mirroring exactly what ServiceKindsCollapseSeeder's own
 * kind() does for the other five (platform_service_item_types +
 * platform_service_item_group_type), rather than re-running that seeder -
 * it also rewrites every stored config's allowed_item_types platform-wide,
 * a blast radius this one new key does not need. `menu_services` is also
 * added to data/service_kinds.php's own `kinds` list so that seeder, if it
 * ever does run again, recognises this key instead of stripping it as
 * unknown.
 */
class MenuServicesKindSeeder extends Seeder
{
    private const KEY = 'menu_services';

    private const NAME_AR = 'خدمات';

    private const NAME_EN = 'Services';

    private const BRANCH_KEY = 'menu_kinds';

    public function run(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('menu service not found.');

            return;
        }

        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', self::BRANCH_KEY)
            ->value('id');

        if ($branchId <= 0) {
            $this->command?->warn('menu_kinds branch not found.');

            return;
        }

        $id = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('key', self::KEY)
            ->value('id');

        if ($id) {
            DB::table('platform_service_item_types')->where('id', $id)->update([
                'name_ar' => self::NAME_AR,
                'name_en' => self::NAME_EN,
                'is_active' => 1,
                'updated_at' => now(),
            ]);
        } else {
            $id = DB::table('platform_service_item_types')->insertGetId([
                'platform_service_id' => $serviceId,
                'key' => self::KEY,
                'name_ar' => self::NAME_AR,
                'name_en' => self::NAME_EN,
                'is_default' => 0,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('platform_service_item_group_type')->insertOrIgnore([
            'group_id' => $branchId,
            'item_type_id' => (int) $id,
        ]);

        $this->command?->info("menu_services kind ready (#{$id}), linked to branch #{$branchId}.");
    }
}
