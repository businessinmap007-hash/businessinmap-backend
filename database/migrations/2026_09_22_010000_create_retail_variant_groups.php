<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own product family — "قميص كلاسيك" — sold as several
 * separately-priced/stocked catalog listings (one per color/size), grouped
 * here purely for DISCOVERY: the customer sees one card and picks a variant,
 * but the chosen variant's own `business_catalog_listings` row is what
 * actually goes in the cart, unchanged. A listing belongs to at most one
 * group (unique on business_catalog_listing_id) — grouped elsewhere or
 * ungrouped are the only two states.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_variant_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('retail_variant_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retail_variant_group_id')->index();
            $table->unsignedBigInteger('business_catalog_listing_id')->unique();
            // "أزرق - مقاس M" — the group already carries the product's own name.
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('retail_variant_group_id')->references('id')->on('retail_variant_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_variant_options');
        Schema::dropIfExists('retail_variant_groups');
    }
};
