<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops five orphan business-product tables that arrived with the external
 * catalog dump and were never wired into the codebase (zero references across
 * app/, routes/, resources/, database/, tests/ — verified 2026-07-12; the only
 * content was 3 hand-made test rows for business 4091 in
 * business_catalog_products). The live per-business retail table is
 * business_catalog_listings (2026_07_08 migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Children first (FKs point at their parents).
        Schema::dropIfExists('business_catalog_product_images');
        Schema::dropIfExists('business_catalog_products');
        Schema::dropIfExists('business_product_images');
        Schema::dropIfExists('business_product_sale_snapshots');
        Schema::dropIfExists('business_products');
    }

    public function down(): void
    {
        // Intentionally irreversible: the tables were unreferenced dump
        // artifacts; recreating them empty would serve nothing.
    }
};
