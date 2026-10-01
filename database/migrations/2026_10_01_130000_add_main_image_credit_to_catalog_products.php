<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who took `main_image` and under which licence — «اذا كان هناك صور مفتوحة
 * المصدر اضفها» (المالك، 2026-10-01). The device images come from Wikimedia
 * Commons, and most of its licences (CC BY, CC BY-SA) are only open on
 * condition that the author is credited. Null for a photo with no such
 * condition (the merchant's own, Open Food Facts' public-domain shots).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('catalog_products', 'main_image_credit')) {
            Schema::table('catalog_products', function (Blueprint $table) {
                $table->string('main_image_credit', 255)->nullable()->after('main_image');
            });
        }
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->dropColumn('main_image_credit');
        });
    }
};
