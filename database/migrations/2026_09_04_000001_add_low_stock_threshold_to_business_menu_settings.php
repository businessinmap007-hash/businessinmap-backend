<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null = no alert until the item is fully out (the existing behaviour).
     * Set once per business, applies regardless of the item's own sale unit
     * (a "5" means 5 كجم for a weighed item and 5 عبوة for a packaged one).
     */
    public function up(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('default_margin_percent');
        });
    }

    public function down(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
