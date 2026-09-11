<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A retail listing's own maximum order quantity - the counterpart to
 * `min_order_qty` (see that migration's docblock). Caps how much of THIS
 * listing a single order may take, so one wholesale buyer can't clear out
 * the whole shelf in one checkout and shut everyone else out. Independent
 * of `min_order_qty`: a merchant may set either, both, or neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_catalog_listings', 'max_order_qty')) {
            return;
        }

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->unsignedInteger('max_order_qty')->nullable()->after('min_order_qty');
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->dropColumn('max_order_qty');
        });
    }
};
