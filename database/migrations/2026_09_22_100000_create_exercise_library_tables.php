<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shared catalogue of exercises a trainer picks from when building a plan or
 * a template, instead of typing every exercise by hand. Sections
 * (exercise_categories: chest, back, legs…) hold exercises; each exercise also
 * carries a `kind` (strength / cardio / flexibility…) and its `equipment`, so
 * the picker can filter along either axis.
 *
 * plan_exercises / template_exercises keep their own `name`, sets and reps —
 * a plan must not change when the catalogue is edited — and only remember
 * which catalogue entry they were made from (nullable, nulled if it is
 * deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exercise_library', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_category_id')->constrained('exercise_categories')->cascadeOnDelete();
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            $table->string('kind', 24)->default('strength');
            $table->string('equipment', 24)->nullable();
            $table->unsignedSmallInteger('default_sets')->nullable();
            $table->string('default_reps', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['exercise_category_id', 'is_active']);
            $table->index(['kind', 'is_active']);
        });

        foreach (['plan_exercises', 'template_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('library_exercise_id')->nullable()
                    ->constrained('exercise_library')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['plan_exercises', 'template_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('library_exercise_id');
            });
        }

        Schema::dropIfExists('exercise_library');
        Schema::dropIfExists('exercise_categories');
    }
};
