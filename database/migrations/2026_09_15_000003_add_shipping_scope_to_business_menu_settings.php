<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A retail/wholesale business's "شحن" (shipping) sub-choice, shown only
     * once `supports_delivery` is on for a business that actually sells
     * goods (see BusinessMenuSetting::labelsFor()) - replaces the old
     * "التسليم والاستلام" option group's توصيل/شحن distinction. Both default
     * true for the same opt-out reason supports_delivery/supports_pickup do.
     */
    public function up(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->boolean('supports_international_shipping')->default(true)->after('supports_pickup');
            $table->boolean('supports_domestic_shipping')->default(true)->after('supports_international_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('business_menu_settings', function (Blueprint $table) {
            $table->dropColumn(['supports_international_shipping', 'supports_domestic_shipping']);
        });
    }
};
