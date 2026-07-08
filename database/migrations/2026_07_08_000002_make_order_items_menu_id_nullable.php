<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * order_items.menu_id was NOT NULL, which blocked non-menu offerings
 * (retail catalog listings) since those carry no menu_id. Make it
 * nullable so a line can reference any offering via offering_type/id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_items', 'menu_id')) {
            DB::statement('ALTER TABLE `order_items` MODIFY `menu_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Revert to NOT NULL only if no rows would violate it.
        if (Schema::hasColumn('order_items', 'menu_id')
            && DB::table('order_items')->whereNull('menu_id')->doesntExist()) {
            DB::statement('ALTER TABLE `order_items` MODIFY `menu_id` BIGINT UNSIGNED NOT NULL');
        }
    }
};
