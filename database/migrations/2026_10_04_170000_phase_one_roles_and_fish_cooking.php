<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase one of re-ordering the option groups — المالك، 2026-10-04: «سطر المنتج / سطر مكوّن / وصفى /
 * مغيّر سعر / شروط المتجر», decided PER TRADE (the placement), never per group.
 *
 *   • شروط المتجر — «الاستبدال والإرجاع»، «الحد الأدنى للطلب»، «نطاق التعامل»، «التسليم والاستلام» were
 *     asked per ITEM as «descriptive» fields (69 trades repeated the same copy). They are policies of
 *     the store: usage `store_terms`, chosen once in the profile.
 *   • مكوّن — the wood of a bedroom and the fabric of a sheet are what the item is MADE OF: usage
 *     `component` (still chosen per item, listed on its page). A timber merchant keeps the wood as
 *     his product line (`section`) — untouched.
 *   • طريقة الطهي — «تجهيز السمك» was a guess. Preparing the fish is a service of the SHOP with its own
 *     price per fish: نيء، مشوي، مقلي، سينية بالفرن، شوربة.
 *
 * «الدفع والسداد» is deliberately NOT touched: it is a price changer, but the app and the cart carry
 * ONE price axis per line, so it needs its own decision (see the report of this phase).
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        if ($menu <= 0) {
            return;
        }
        $id = fn (string $name) => (int) DB::table('option_groups')->where('name_ar', $name)->value('id');

        // ── شروط المتجر ────────────────────────────────────────────────────
        $terms = array_filter([$id('نطاق التعامل'), $id('الاستبدال والإرجاع'), $id('الحد الأدنى للطلب'), $id('التسليم والاستلام')]);
        DB::table('service_option_group_placements')
            ->where('platform_service_id', $menu)->whereIn('option_group_id', $terms)->whereIn('usage', ['descriptive', 'store_cart'])
            ->update(['usage' => 'store_terms', 'updated_at' => now()]);

        // ── مكوّن ──────────────────────────────────────────────────────────
        $wood = $id('أنواع الأخشاب');
        DB::table('service_option_group_placements')
            ->where('platform_service_id', $menu)->where('option_group_id', $wood)->where('usage', 'descriptive')
            ->update(['usage' => 'component', 'updated_at' => now()]);
        $fabric = $id('أنواع الأقمشة');
        DB::table('service_option_group_placements')
            ->where('platform_service_id', $menu)->where('option_group_id', $fabric)->where('usage', 'descriptive')
            ->whereIn('child_id', [115, 59, 60])
            ->update(['usage' => 'component', 'updated_at' => now()]);

        // ── طريقة الطهي ────────────────────────────────────────────────────
        $group = $id('تجهيز السمك');
        if ($group) {
            DB::table('option_groups')->where('id', $group)->update(['name_ar' => 'طريقة الطهي', 'name_en' => 'Cooking method', 'updated_at' => now()]);
            $methods = [['نيء (بدون طهي)', 'Raw (uncooked)'], ['مشوي', 'Grilled'], ['مقلي', 'Fried'], ['سينية بالفرن', 'Baked in tray'], ['شوربة', 'Fish soup']];
            foreach (DB::table('options')->where('group_id', $group)->orderBy('sort_order')->orderBy('id')->get(['id']) as $i => $option) {
                if (! isset($methods[$i])) {
                    continue;
                }
                [$ar, $en] = $methods[$i];
                DB::table('options')->where('id', $option->id)->update([
                    'name_ar' => $ar,
                    'name_en' => DB::table('options')->where('name_en', $en)->where('id', '!=', $option->id)->exists() ? $en . ' (fish)' : $en,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};
