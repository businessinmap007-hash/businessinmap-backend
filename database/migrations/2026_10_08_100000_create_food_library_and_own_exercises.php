<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «جدول التغذية وجدول التمارين العلاجية … ليختار منها المتخصص مباشرة الاكل او التمرين مع امكانية اضافة من المتخصص
 * لنوع غذاء او تمرين» — المالك، 2026-10-08.
 *
 * A food catalogue the nutrition specialist (or any trainer) picks from instead of typing a meal, beside the exercise
 * catalogue that already existed. Both carry an optional `owner_id`: NULL is the shared catalogue, curated by the
 * platform; a user id is a specialist's OWN entry — visible to them alone, theirs to edit and delete. A plan keeps its
 * own name and numbers (a plan must not change when the catalogue does) and only remembers which entry it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('food_library', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_category_id')->constrained('food_categories')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            // «رغيف (90 جم)» — what one serving is, and what the numbers below are for
            $table->string('serving_label', 80);
            $table->unsignedSmallInteger('serving_grams')->nullable();
            $table->unsignedSmallInteger('calories');
            $table->decimal('protein_g', 6, 1)->default(0);
            $table->decimal('carbs_g', 6, 1)->default(0);
            $table->decimal('fat_g', 6, 1)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['food_category_id', 'is_active']);
            $table->index('owner_id');
        });

        foreach (['plan_meals', 'template_meals'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('food_id')->nullable()->constrained('food_library')->nullOnDelete();
                // how many servings of the food — the meal's calories are the food's × this
                $table->decimal('servings', 5, 2)->default(1);
            });
        }

        Schema::table('exercise_library', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            // the specialist's own instruction for doing it, copied onto the plan's exercise as its note
            $table->string('instructions', 255)->nullable();
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_library', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('instructions');
        });

        foreach (['plan_meals', 'template_meals'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('food_id');
                $table->dropColumn('servings');
            });
        }

        Schema::dropIfExists('food_library');
        Schema::dropIfExists('food_categories');
    }
};
