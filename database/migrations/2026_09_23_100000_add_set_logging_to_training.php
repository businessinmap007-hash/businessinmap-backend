<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a confirmed round from "I did set 2 of 3" into a record of what was
 * actually lifted: the reps done and the weight used, per set, next to the
 * weight the trainer prescribed. Numbers only — the training section still has
 * no free text or images from the trainee (see the rounds migration).
 *
 * plan_session_completions is written once when every exercise scheduled for a
 * day has all its sets confirmed. It is what tells the trainer «finished today»
 * and it is one row per plan per day, so the monthly summary can count sessions
 * without re-deriving them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['plan_exercises', 'template_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('target_weight', 6, 2)->nullable()->after('reps');
            });
        }

        Schema::table('plan_exercise_rounds', function (Blueprint $table) {
            $table->unsignedSmallInteger('reps')->nullable()->after('round_number');
            $table->decimal('weight', 6, 2)->nullable()->after('reps');
        });

        Schema::create('plan_session_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_plan_id')->index();
            $table->unsignedBigInteger('client_id')->index();
            $table->date('for_date');
            $table->unsignedSmallInteger('exercises_count')->default(0);
            $table->unsignedSmallInteger('sets_count')->default(0);
            $table->unsignedInteger('total_reps')->default(0);
            $table->decimal('volume_kg', 12, 2)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['training_plan_id', 'for_date'], 'plan_session_completion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_session_completions');

        Schema::table('plan_exercise_rounds', function (Blueprint $table) {
            $table->dropColumn(['reps', 'weight']);
        });

        foreach (['plan_exercises', 'template_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('target_weight');
            });
        }
    }
};
