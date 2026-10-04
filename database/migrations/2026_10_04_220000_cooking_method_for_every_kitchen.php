<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «طريقة الطهى ممكن تستخدم مع مطاعم وتجهيز لحوم ودواجن» — المالك، 2026-10-04. The cooking method was the
 * fish shop's; it is a service of any KITCHEN:
 *
 *   • the fish shop, the butcher and the poultry shop cook what they sell — it rides on every item (`all`);
 *   • a restaurant sells dishes, most of which are not cooked "by method" — its merchant chooses the items
 *     that offer it (`chosen`), per item.
 *
 * The prices stay the shop's own, set once (business_addon_prices).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_option_group_placements', 'item_scope')) {
            Schema::table('service_option_group_placements', function (Blueprint $table) {
                // all = every item of the shop carries the service; chosen = only the items the merchant ticks.
                $table->string('item_scope', 10)->default('all');
            });
        }
        if (! Schema::hasTable('menu_item_addon_choices')) {
            Schema::create('menu_item_addon_choices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('menu_item_id');
                $table->unsignedBigInteger('option_group_id');
                $table->timestamps();
                $table->unique(['menu_item_id', 'option_group_id']);
            });
        }

        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $group = (int) DB::table('option_groups')->where('name_ar', 'طريقة الطهي')->value('id');
        if (! $menu || ! $group) {
            return;
        }

        // «مسلوق» — a butcher and a restaurant boil as well as grill.
        if (! DB::table('options')->where('group_id', $group)->where('name_ar', 'مسلوق')->exists()) {
            DB::table('options')->insert([
                'group_id' => $group, 'sort_order' => 5, 'name_ar' => 'مسلوق',
                'name_en' => DB::table('options')->where('name_en', 'Boiled')->exists() ? 'Boiled (cooking)' : 'Boiled',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // The kitchens that cook what they sell: every item. Restaurants: the items the merchant chooses.
        foreach ([[101, 'all'], [553, 'all'], [229, 'all'], [245, 'chosen'], [246, 'chosen'], [108, 'chosen'], [143, 'chosen']] as $i => [$child, $scope]) {
            DB::table('service_option_group_placements')->updateOrInsert(
                ['platform_service_id' => $menu, 'option_group_id' => $group, 'child_id' => $child, 'item_type_key' => ''],
                ['usage' => 'addon', 'item_scope' => $scope, 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 90 + $i, 'show_on_page' => 1, 'display' => 'auto', 'multiple' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
    }
};
