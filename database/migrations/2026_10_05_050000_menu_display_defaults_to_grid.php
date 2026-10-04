<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «اجعل الوضع الافتراضى فى العرض شبكة وليس قائمة» — المالك، 2026-10-05. A menu shows as a grid unless its
 * business chose a list: the column default and the fallbacks say grid. A business that already has a row
 * keeps what it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE business_menu_settings MODIFY display_mode VARCHAR(10) NOT NULL DEFAULT 'grid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE business_menu_settings MODIFY display_mode VARCHAR(10) NOT NULL DEFAULT 'list'");
    }
};
