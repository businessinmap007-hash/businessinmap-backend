<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `option_groups.detail_type` and `menu_detail_profiles.detail_type` were created with the table's
 * collation, `menu_detail_types.code` with the connection's: joining them failed ("illegal mix of
 * collations"). One collation for the three.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['option_groups', 'menu_detail_profiles'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY detail_type VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        }
    }

    public function down(): void
    {
    }
};
