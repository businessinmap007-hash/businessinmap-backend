<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supermarket menu-settings screen, scoped 2026-08-25 («حدٌّ أدنى للطلب،
 * وهامش ربح افتراضى فوق السعر الإرشادى»):
 *
 * - `min_order_amount`: NULL means no minimum (unchanged behaviour). When set,
 *   checkout refuses an order whose MENU subtotal alone falls short — see
 *   CustomerCartService::placeOrder(). Retail lines in the same cart are not
 *   counted; this is a menu-service setting, not a cart-wide one.
 * - `default_margin_percent`: NULL means no default. When set, the shelf-fill
 *   screen (MenuMarketCatalogController) computes a row's selling price from
 *   its supply price automatically when the owner leaves the price blank,
 *   instead of treating a blank price as "clear this row" — see
 *   MenuMarketCatalogController::update().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_menu_settings')) {
            return;
        }

        Schema::table('business_menu_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('business_menu_settings', 'min_order_amount')) {
                $table->decimal('min_order_amount', 10, 2)->nullable()->after('tax_rate_percent');
            }

            if (! Schema::hasColumn('business_menu_settings', 'default_margin_percent')) {
                $table->decimal('default_margin_percent', 5, 2)->nullable()->after('min_order_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_menu_settings')) {
            return;
        }

        Schema::table('business_menu_settings', function (Blueprint $table) {
            if (Schema::hasColumn('business_menu_settings', 'default_margin_percent')) {
                $table->dropColumn('default_margin_percent');
            }

            if (Schema::hasColumn('business_menu_settings', 'min_order_amount')) {
                $table->dropColumn('min_order_amount');
            }
        });
    }
};
