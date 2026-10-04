<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «الاسم صنية بالفرن وليس سينية، وأضف مشوي جريل ومشوي زيت وليمون — وما يتم تسعيره هو ما يظهر فى الاختيارات»
 * — المالك، 2026-10-05. The tray is «صنية»; the grill has two more ways of being served. The shop prices
 * the ways it offers and ONLY those reach the customer's choices (an unpriced one is simply not offered).
 */
return new class extends Migration
{
    public function up(): void
    {
        $group = (int) DB::table('option_groups')->where('name_ar', 'طريقة الطهي')->value('id');
        if (! $group) {
            return;
        }
        $now = now();

        DB::table('options')->where('group_id', $group)->where('name_ar', 'سينية بالفرن')
            ->update(['name_ar' => 'صنية بالفرن', 'updated_at' => $now]);

        foreach ([['مشوي جريل', 'Grilled on the grill'], ['مشوي زيت وليمون', 'Grilled with oil and lemon']] as [$ar, $en]) {
            if (! DB::table('options')->where('group_id', $group)->where('name_ar', $ar)->exists()) {
                DB::table('options')->insert([
                    'group_id' => $group, 'sort_order' => 99, 'name_ar' => $ar, 'name_en' => $en,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // The grills sit together.
        foreach (['نيء (بدون طهي)', 'مشوي', 'مشوي جريل', 'مشوي زيت وليمون', 'مقلي', 'صنية بالفرن', 'شوربة', 'مسلوق'] as $i => $name) {
            DB::table('options')->where('group_id', $group)->where('name_ar', $name)->update(['sort_order' => $i]);
        }
    }

    public function down(): void
    {
    }
};
