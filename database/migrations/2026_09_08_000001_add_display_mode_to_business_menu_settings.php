<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How this business's menu renders for a customer — a plain row per item
 * ('list', the default — what every menu already looked like) or a 2-column
 * card grid ('grid', better suited to a goods catalog with photos). One
 * merchant-wide switch, not per-section: a shop either wants photos up
 * front or it doesn't.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->string('display_mode', 10)->default('list')->after('supports_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }
};
