<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the price is the price OF.
 *
 * «الصنف المسعّر يمكن اضافته للمنيو … مثلا منتج يباع فى محل يكون بالوزن، كيلو
 *  أو لتر» — المالك، 2026-08-23.
 *
 * A menu row carried a name and a number and nothing between them, so «طماطم —
 * ٤٥» is forty-five pounds for a kilo, for a crate, or for one tomato, and the
 * customer finds out at the counter. Every trade the owner named for this —
 * vegetables, fruit, fish, shrimp — is sold by weight, and none of them could
 * say so.
 *
 * A code from `catalog_units`, which already holds the vocabulary (كجم، لتر،
 * قطعة، علبة) for the shared product catalog. Nullable, and null means «by the
 * item», which is what a sandwich is and what most menus are.
 *
 * NOT added to `business_service_prices` in the same breath. A service is
 * priced per visit, per hour or per night, and it already says which through
 * `charge_mode` and `duration_minutes` — a second unit column there would be a
 * third way to answer a question that has two.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || Schema::hasColumn('menu_items', 'sale_unit')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('sale_unit', 20)->nullable()->after('base_price');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_items', 'sale_unit')) {
            Schema::table('menu_items', fn (Blueprint $table) => $table->dropColumn('sale_unit'));
        }
    }
};
