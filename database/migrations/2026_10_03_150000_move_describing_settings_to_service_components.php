<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One home for the DESCRIPTIVE fields: «مكونات الخدمة». Which groups describe an
 * item, in what order, whether the customer's product page shows them, how the
 * merchant's form draws them (buttons / dropdown) and whether one or several can
 * be chosen are all settings of the PLACEMENT — per trade — instead of a second
 * list on the menu kind in «أشكال المنيو» that could contradict it.
 *
 * Carries over what was chosen on the kinds: each choice's settings go to the
 * `descriptive` placements of that group (the group's other placements — a
 * price_variant, say — keep their meaning, nothing is flipped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_option_group_placements', function (Blueprint $table) {
            $table->boolean('show_on_page')->default(true);
            $table->string('display', 12)->default('auto');
            $table->boolean('multiple')->default(true);
        });

        if (! Schema::hasTable('menu_detail_profile_option_groups')) {
            return;
        }

        foreach (DB::table('menu_detail_profile_option_groups')->get() as $choice) {
            DB::table('service_option_group_placements')
                ->where('option_group_id', $choice->option_group_id)
                ->where('usage', 'descriptive')
                ->update([
                    'show_on_page' => $choice->show_on_page,
                    'display' => $choice->display,
                    'multiple' => $choice->multiple,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('service_option_group_placements', function (Blueprint $table) {
            $table->dropColumn(['show_on_page', 'display', 'multiple']);
        });
    }
};
