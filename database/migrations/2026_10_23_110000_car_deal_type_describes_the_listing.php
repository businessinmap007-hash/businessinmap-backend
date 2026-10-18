<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «نوع التعامل» (بيع / إيجار / تبديل) is how a car listing says what it is FOR.
 *
 * For the showroom it was a price axis (one price per option) and for the private seller it was not placed at all, so
 * neither could tick it on the item form. It now DESCRIBES the listing — one choice, first — and the unit's fields
 * follow the choice (a sale asks the mileage, a rental asks the driver, the insurance and the rates; see
 * CarRentalSpecsSeeder). Done once, as a migration: a menu shape belongs to the admin's «أشكال المنيو» afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $group = (int) DB::table('option_groups')->where('name_ar', 'نوع التعامل')->value('id');

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
                ['usage' => 'descriptive', 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 5, 'show_on_page' => 1, 'display' => 'chips', 'multiple' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // a curated shape: nothing to undo by guesswork
    }
};
