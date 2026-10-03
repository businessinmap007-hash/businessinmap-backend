<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * «اربط ما اسفل الصورة بما هو فى حقول أستطيع إضافته أو إخفاؤه من الحقول المختارة» — المالك،
 * 2026-10-04. The description box of the add-product form IS the «الوصف» field of the kind:
 * ticked, the box shows (and the text shows on the page / card as the kind says); unticked,
 * it is gone. Every kind that exists today already shows the box, so each one gets the field
 * ticked for the page — the admin unticks it where it does not belong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $attribute = DB::table('catalog_attributes')->where('code', 'description')->value('id');
        if (! $attribute) {
            return;
        }

        foreach (DB::table('menu_detail_profiles')->pluck('id') as $profileId) {
            if (DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $profileId)->where('catalog_attribute_id', $attribute)->exists()) {
                continue;
            }

            DB::table('menu_detail_profile_attributes')->insert([
                'menu_detail_profile_id' => $profileId,
                'catalog_attribute_id' => $attribute,
                'sort_order' => 900,
                'show_on_card' => 0,
                'per_item' => 0,
                'is_filterable' => 0,
                'show_on_page' => 1,
                'display' => 'auto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The field rows are the admin's to keep or drop; nothing to undo.
    }
};
