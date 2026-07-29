<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A lab list item may now also reference a category child (a business
 * sub-classification, e.g. a medical specialty in category_children_master),
 * alongside options and service item types. The owner wants the الصحة list to
 * hold the 44 medical specialties as its selectable pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `lab_list_items` MODIFY `source` ENUM('option','item_type','category_child') NOT NULL");
    }

    public function down(): void
    {
        // Drop any category_child rows first so the narrower enum still fits.
        DB::table('lab_list_items')->where('source', 'category_child')->delete();
        DB::statement("ALTER TABLE `lab_list_items` MODIFY `source` ENUM('option','item_type') NOT NULL");
    }
};
