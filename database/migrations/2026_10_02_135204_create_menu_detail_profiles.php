<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «منيو أساسي» vs «منيو تفصيلي» — the owner's rule (2026-10-02). Every
 * priced option group is one or the other: a basic menu (produce, spices,
 * groceries — name, price, quantity) or a detailed one, and a detailed one
 * names WHICH details (mobiles, computers, laptops, cars…). The profile's
 * attributes are what «التسعير والتفاصيل» shows, what the product page
 * lists, and what search will filter on to compare shops' prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_detail_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_detail_profile_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_detail_profile_id')->constrained('menu_detail_profiles')->cascadeOnDelete();
            $table->foreignId('catalog_attribute_id')->constrained('catalog_attributes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            // The few that make the card's one-line summary
            // («Core i7 · 16GB · 512GB SSD»), not the whole table.
            $table->boolean('show_on_card')->default(false);
            // Offered as a search filter for comparing shops.
            $table->boolean('is_filterable')->default(true);
            $table->timestamps();

            $table->unique(['menu_detail_profile_id', 'catalog_attribute_id'], 'menu_detail_profile_attr_unique');
        });

        Schema::table('option_groups', function (Blueprint $table) {
            // NULL = «منيو أساسي».
            $table->foreignId('menu_detail_profile_id')->nullable()->after('price_role')
                ->constrained('menu_detail_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_detail_profile_id');
        });

        Schema::dropIfExists('menu_detail_profile_attributes');
        Schema::dropIfExists('menu_detail_profiles');
    }
};
