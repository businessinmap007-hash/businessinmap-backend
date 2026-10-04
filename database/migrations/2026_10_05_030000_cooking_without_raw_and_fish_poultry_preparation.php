<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الغى نيء بدون طهى لانه السعر الاساسى — ممكن مع الاسماك والفراخ تضيف تنظيف وتقطيع» — المالك، 2026-10-05.
 *
 *   • «نيء (بدون طهي)» is the item's own price, not a service: it leaves the cooking methods (its prices and
 *     the extras that were synced from it go with it).
 *   • «التجهيز» — a service of the fish shop and the poultry shop, priced once by the shop like the cooking
 *     method: تنظيف، تقطيع، تنظيف وتقطيع. It rides on every item; it is a separate group, so it combines
 *     with a cooking method.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $cooking = (int) DB::table('option_groups')->where('name_ar', 'طريقة الطهي')->value('id');

        if ($cooking) {
            foreach (DB::table('options')->where('group_id', $cooking)->where('name_ar', 'نيء (بدون طهي)')->pluck('id') as $raw) {
                DB::table('menu_item_extras')->where('source_option_id', $raw)->delete();
                DB::table('business_addon_prices')->where('option_id', $raw)->delete();
                DB::table('option_user')->where('option_id', $raw)->delete();
                DB::table('options')->where('id', $raw)->delete();
            }
            foreach (['مشوي', 'مشوي جريل', 'مشوي زيت وليمون', 'مقلي', 'صنية بالفرن', 'شوربة', 'مسلوق'] as $i => $name) {
                DB::table('options')->where('group_id', $cooking)->where('name_ar', $name)->update(['sort_order' => $i]);
            }
        }

        $group = (int) DB::table('option_groups')->where('name_ar', 'التجهيز')->value('id');
        if (! $group) {
            $group = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => 'التجهيز', 'name_en' => 'Preparation', 'reorder' => 10310, 'is_active' => 1,
                'price_role' => 'modifier', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach ([['تنظيف', 'Cleaning'], ['تقطيع', 'Cutting'], ['تنظيف وتقطيع', 'Cleaning and cutting']] as $i => [$ar, $en]) {
            if (! DB::table('options')->where('group_id', $group)->where('name_ar', $ar)->exists()) {
                DB::table('options')->insert([
                    'group_id' => $group, 'sort_order' => $i, 'name_ar' => $ar,
                    'name_en' => DB::table('options')->where('name_en', $en)->exists() ? $en . ' (preparation)' : $en,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if ($menu) {
            // The fish shop and the poultry shop: every item.
            foreach ([101, 229] as $i => $child) {
                DB::table('service_option_group_placements')->updateOrInsert(
                    ['platform_service_id' => $menu, 'option_group_id' => $group, 'child_id' => $child, 'item_type_key' => ''],
                    ['usage' => 'addon', 'item_scope' => 'all', 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 95 + $i, 'show_on_page' => 1, 'display' => 'auto', 'multiple' => 0, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
    }
};
