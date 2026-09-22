<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu (`menu`) is now «منيو» and retail (`retail`) is now «كاتلوج». Names only:
 * which specialty gets which service is a separate, later redesign.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_services')->where('key', 'menu')->update(['name_ar' => 'منيو', 'name_en' => 'Menu', 'updated_at' => now()]);
        DB::table('platform_services')->where('key', 'retail')->update(['name_ar' => 'كاتلوج', 'name_en' => 'Catalog', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('platform_services')->where('key', 'menu')->update(['name_ar' => 'القائمة', 'name_en' => 'Menu', 'updated_at' => now()]);
        DB::table('platform_services')->where('key', 'retail')->update(['name_ar' => 'التجزئة', 'name_en' => 'Retail', 'updated_at' => now()]);
    }
};
