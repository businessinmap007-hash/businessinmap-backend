<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text selling unit for a retail listing (e.g. "kg", "كرتونة", "طن") —
 * shown wherever min_order_qty is, so "20" reads as "20 كيلو" instead of a
 * bare number. Free text, not a closed set: a merchant's own wholesale unit
 * ("شوال", "دستة", ...) is theirs to name, not ours to enumerate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_catalog_listings', 'unit')) {
            return;
        }

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->after('min_order_qty');
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
