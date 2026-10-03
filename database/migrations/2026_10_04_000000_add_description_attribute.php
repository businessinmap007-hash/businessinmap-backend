<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * «الوصف» as a field a detail kind can choose, like «اللون» or «عدد القطع» — المالك،
 * 2026-10-04. The merchant types the description in the add-product form (it lives on the
 * item itself); this row only makes it selectable in «أشكال المنيو», where the admin
 * decides whether it shows on the CARD and/or the product PAGE. Without a row for it the
 * description keeps showing on the page, never on the card — what it always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('catalog_attributes')->where('code', 'description')->exists()) {
            return;
        }

        DB::table('catalog_attributes')->insert([
            'code' => 'description',
            'name_ar' => 'الوصف',
            'name_en' => 'Description',
            'data_type' => 'text',
            'unit_id' => null,
            'is_filterable' => 0,
            'is_variant_axis' => 0,
            'is_required' => 0,
            'sort_order' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('catalog_attributes')->where('code', 'description')->value('id');
        if ($id) {
            DB::table('menu_detail_profile_attributes')->where('catalog_attribute_id', $id)->delete();
            DB::table('catalog_attributes')->where('id', $id)->delete();
        }
    }
};
