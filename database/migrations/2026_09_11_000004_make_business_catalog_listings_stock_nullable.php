<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `stock` was NOT NULL default 0 — meaning "left blank" and "sold out" were
 * the same value, and enforcing stock at checkout (see
 * CustomerCartService::assertAndDecrementRetailStock) would have made every
 * listing a merchant never bothered to set a quantity for instantly
 * unorderable. Null now means "not tracked" (unlimited), matching how the
 * app already reads it (RetailStorefrontListing.stock is nullable, and
 * "out of stock" is null-checked before the <= 0 comparison everywhere it's
 * displayed) — the column just never actually carried that meaning yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_catalog_listings', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->change();
        });
    }
};
