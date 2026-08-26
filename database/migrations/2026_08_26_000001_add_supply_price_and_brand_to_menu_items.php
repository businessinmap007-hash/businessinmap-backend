<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two columns a shelf-catalog row needs beside `base_price`.
 *
 * «سعر التوريد اختيارى وسعر البيع … وعمود اخر اذا كان هناك اسم الشركة المنتجة
 *  او الماركة اختيارى» — المالك، 2026-08-25، عن منيو السوبر ماركت والهايبر
 *  والمني ماركت.
 *
 * `supply_price` is the cost the merchant paid, never sent to a customer —
 * `MenuItemResource` and `MenuDiscoveryController` are hand-written whitelists,
 * so leaving it off both is enough to keep it internal. `brand_name` is the
 * opposite: written for the customer to read, same as the name and the price.
 *
 * Both nullable: a sandwich shop's items will never carry either.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'supply_price')) {
                $table->decimal('supply_price', 12, 2)->nullable()->after('base_price');
            }

            if (! Schema::hasColumn('menu_items', 'brand_name')) {
                $table->string('brand_name', 191)->nullable()->after('sale_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            foreach (['supply_price', 'brand_name'] as $column) {
                if (Schema::hasColumn('menu_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
