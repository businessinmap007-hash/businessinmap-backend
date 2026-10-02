<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The values a merchant states for ONE unit — a car's year, mileage, gearbox
 * and colour; a laptop's RAM as configured; a phone's colour. A catalog master
 * («Toyota Corolla») carries none of these: the same model is sold as 2018 and
 * as 2023. A detail kind's field flagged `per_item` is entered here, per menu
 * item, and is what search filters and the product page lists beside the
 * master's own specs. See MenuDetailProfile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_detail_profile_attributes', function (Blueprint $table) {
            // true = the merchant states it for each unit; false = it comes
            // from the catalog master.
            $table->boolean('per_item')->default(false)->after('show_on_card');
        });

        Schema::create('menu_item_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('catalog_attributes')->cascadeOnDelete();
            // select → the option; number → value_number; text → value_text.
            $table->unsignedBigInteger('option_id')->nullable();
            $table->decimal('value_number', 16, 4)->nullable();
            $table->string('value_text', 191)->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'attribute_id']);
            // «سيارات بين ٢٠٢٠ و ٢٠٢٣»، «أقل من ٥٠ ألف كم» — a range over one attribute.
            $table->index(['attribute_id', 'value_number']);
            $table->index(['attribute_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_attribute_values');

        Schema::table('menu_detail_profile_attributes', function (Blueprint $table) {
            $table->dropColumn('per_item');
        });
    }
};
