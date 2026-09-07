<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a business accepts delivery / pickup orders at all — the
     * unified fulfillment-method entry point (bim_app, above the menu, before
     * checkout) needs to know which of the three methods to even offer.
     * Both default true: an existing business keeps advertising exactly what
     * it already implicitly offered (checkout's fulfillment_type validation
     * already allowed delivery/pickup/dine_in unconditionally), so this is a
     * pure opt-out, never a silent new restriction. Dine-in has no column
     * here — it's derived from whether the business has any active row in
     * business_tables (BIM-13.3), the existing signal for that.
     */
    public function up(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->boolean('supports_delivery')->default(true)->after('low_stock_threshold');
            $table->boolean('supports_pickup')->default(true)->after('supports_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->dropColumn(['supports_delivery', 'supports_pickup']);
        });
    }
};
