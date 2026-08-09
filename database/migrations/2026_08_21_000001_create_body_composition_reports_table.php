<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The monthly body report a trainer takes for one client.
 *
 * `plan_progress_logs` already holds the client's own check-in — a weight and a
 * note, whenever he feels like it. That is not a report: it says nothing about
 * whether the weight that moved was muscle or water, which is the only question
 * a training plan is actually judged on.
 *
 * One row per plan per MONTH, keyed so it cannot be recorded twice. The trainer
 * takes the measurement — he owns the scale — and the client reads it; that is
 * the same one-way rule the plan's illustrative photos follow, and for the same
 * reason: a plan is two people's private business and only one of them is
 * holding the instrument.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_composition_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            // Denormalised so the client's own history survives a plan ending,
            // and so «my reports» never has to join through the plan.
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('trainer_id')->index();

            // The month the report belongs to, always the 1st. `measured_on` is
            // the day the scale was actually read.
            $table->date('for_month');
            $table->date('measured_on')->nullable();

            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('muscle_mass_kg', 6, 2)->nullable();
            $table->decimal('fat_percent', 5, 2)->nullable();
            $table->decimal('water_percent', 5, 2)->nullable();
            // Kept because a scale that reports the three above reports these
            // too, and a trainer who has them should not have to drop them.
            $table->decimal('bone_mass_kg', 6, 2)->nullable();
            $table->decimal('visceral_fat', 5, 2)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['training_plan_id', 'for_month']);
            $table->index(['client_id', 'for_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_composition_reports');
    }
};
