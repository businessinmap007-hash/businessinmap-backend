<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retail listings: a business sells a product from the shared catalog master.
 * The catalog stays global/deduped; a listing is just (business + master product
 * + price + stock). This is the retail side of the unified offering layer
 * (Phase 3c) — the counterpart of business_service_prices for bespoke items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_catalog_listings')) {
            return;
        }

        Schema::create('business_catalog_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('catalog_product_id');
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'catalog_product_id'], 'bcl_business_product_unique');
            $table->index('catalog_product_id', 'bcl_product_index');
            $table->index(['business_id', 'is_active'], 'bcl_business_active_index');

            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('catalog_product_id')->references('id')->on('catalog_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_catalog_listings');
    }
};
