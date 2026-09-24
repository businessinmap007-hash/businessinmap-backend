<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «كل منتج الان ممكن يكون له 5 اسعار جديد - مستعمل - كسر زيرو - كاش - قسط،
 * كل واحد منهم سطر بيانات كامل» — المالك، 2026-09-24.
 *
 * A listing stays ONE priced row (the cart, stock and checkout all key on the
 * listing id and are untouched). What changes is that a business may now hold
 * several rows for the same product, told apart by the price-variant options
 * it carries: condition (جديد / مستعمل / كسر زيرو) and payment (كاش / قسط),
 * each with its own price, stock and description. 0 means «no such variant».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->unsignedBigInteger('condition_option_id')->default(0)->after('catalog_product_id');
            $table->unsignedBigInteger('payment_option_id')->default(0)->after('condition_option_id');
            $table->text('description_ar')->nullable()->after('unit');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->dropUnique('bcl_business_product_unique');
            $table->unique(
                ['business_id', 'catalog_product_id', 'condition_option_id', 'payment_option_id'],
                'bcl_business_product_variant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->dropUnique('bcl_business_product_variant_unique');
        });

        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->unique(['business_id', 'catalog_product_id'], 'bcl_business_product_unique');
            $table->dropColumn(['condition_option_id', 'payment_option_id', 'description_ar', 'description_en']);
        });
    }
};
