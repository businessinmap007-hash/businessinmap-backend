<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «ربط نظام المواصفات مع المنيو» — a menu item MAY point at a shared
 * catalog master (the same `catalog_products` retail's «كتالوج تفصيلي»
 * already carries specs for — see [[three-catalog-shapes]]). When it does,
 * the menu item's own spec table comes for free from
 * `catalog_product_attribute_values` via `ProductSpecs`, instead of a
 * business retyping «المعالج/الرام/الموديل» by hand for every phone or
 * laptop it sells through the menu surface.
 *
 * Optional and independent of price: a menu item keeps its own
 * `base_price`/`supply_price`/`available_quantity` regardless of whether it
 * links a catalog master — this only supplies the spec table, nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_product_id')->nullable()->after('medicine_id');
            $table->index('catalog_product_id', 'menu_items_catalog_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex('menu_items_catalog_product_idx');
            $table->dropColumn('catalog_product_id');
        });
    }
};
