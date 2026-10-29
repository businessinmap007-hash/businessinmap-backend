<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every item type sits in a branch — the services screens pick types THROUGH branches (ServiceKindsPruneTest). The
 * inventory suite's nine features get one branch of their own, «أجزاء المخزون والمشتريات», so the admin ticks them per
 * category on the same screen as every other service. Its key is checked against every service's branches: keys are
 * matched globally (the `supermarket` landmine).
 */
return new class extends Migration
{
    private const KEY = 'inventory_suite_features';

    public function up(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'inventory')->value('id');

        if ($serviceId <= 0) {
            return;
        }

        DB::table('platform_service_item_groups')->updateOrInsert(
            ['key' => self::KEY],
            ['platform_service_id' => $serviceId, 'name_ar' => 'أجزاء المخزون والمشتريات', 'name_en' => 'Inventory & purchases parts', 'sort_order' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        );
        $groupId = (int) DB::table('platform_service_item_groups')->where('key', self::KEY)->value('id');

        foreach (DB::table('platform_service_item_types')->where('platform_service_id', $serviceId)->pluck('id') as $typeId) {
            $exists = DB::table('platform_service_item_group_type')->where('group_id', $groupId)->where('item_type_id', $typeId)->exists();
            if (! $exists) {
                DB::table('platform_service_item_group_type')->insert(['group_id' => $groupId, 'item_type_id' => $typeId]);
            }
        }
    }

    public function down(): void
    {
        $groupId = DB::table('platform_service_item_groups')->where('key', self::KEY)->value('id');

        if ($groupId) {
            DB::table('platform_service_item_group_type')->where('group_id', $groupId)->delete();
            DB::table('platform_service_item_groups')->where('id', $groupId)->delete();
        }
    }
};
