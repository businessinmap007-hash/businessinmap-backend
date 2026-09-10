<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A retail listing's own minimum order quantity (e.g. 20 كيلو out of 500 في
 * المخزون) — wholesale buyers care about how much of THIS product they take,
 * not a currency total across an unrelated mix of items in the same cart.
 * Replaces the business-wide `business_retail_settings.min_order_amount`
 * dropped alongside this (see the next migration): that shape didn't fit how
 * a wholesale minimum is actually expressed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_catalog_listings', 'min_order_qty')) {
            return;
        }

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->unsignedInteger('min_order_qty')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->dropColumn('min_order_qty');
        });
    }
};
