<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «ماركات السيارات» describes the car — it is not a second shelf and not a price axis.
 *
 * A car is «سيدان — كيا»: the kind of car is what is sold (the line, the section), the make is one word that describes
 * THAT car. On the showroom's and the private seller's menu it had been a price axis («السعر حسب ماركات السيارات»: one
 * price per make) and the screen listed it as a second section beside «نوع المركبة». It now describes the listing — one
 * choice, from a dropdown (there are 43 makes) — right after the deal type. Done once, as a migration: a menu shape
 * belongs to the admin's «أشكال المنيو» afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $group = (int) DB::table('option_groups')->where('name_ar', 'ماركات السيارات')->value('id');

        if ($menu <= 0 || $group <= 0) {
            return;
        }

        foreach (['معرض سيارات', 'سيارة من المالك'] as $name) {
            $child = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

            if ($child <= 0) {
                continue;
            }

            DB::table('service_option_group_placements')->updateOrInsert(
                ['platform_service_id' => $menu, 'option_group_id' => $group, 'child_id' => $child, 'item_type_key' => ''],
                ['usage' => 'descriptive', 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 10, 'show_on_page' => 1, 'display' => 'dropdown', 'multiple' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // a curated shape: nothing to undo by guesswork
    }
};
