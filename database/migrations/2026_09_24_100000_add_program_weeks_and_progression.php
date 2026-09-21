<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plan becomes a programme over weeks. The trainer defines the exercises ONCE
 * (the week's template — Push / Pull / Legs on their days), picks how many weeks
 * it runs, and says how the weights climb; nothing is copied per week. The
 * weight for any week is computed from the rule (see App\Support\ExerciseProgression):
 *
 *   base weights per set (set_weights, e.g. 20-25-30, or the flat target_weight)
 *   + floor((week - 1) / progress_every_weeks) * progress_increment_kg
 *
 * so 20-25-30 for the first two weeks becomes 25-30-35 for the next two with
 * "every 2 weeks, +5 kg". Editing the rule re-plans every future week at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['plan_exercises', 'template_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('set_weights')->nullable()->after('target_weight');
                $table->unsignedTinyInteger('progress_every_weeks')->nullable()->after('set_weights');
                $table->decimal('progress_increment_kg', 5, 2)->nullable()->after('progress_every_weeks');
                // «Push» / «Pull» / «Legs» — what a training day is called.
                $table->string('day_label', 40)->nullable()->after('day_of_week');
            });
        }

        Schema::table('training_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_weeks')->nullable()->after('ends_on');
        });

        Schema::table('training_plan_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_weeks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_templates', function (Blueprint $table) {
            $table->dropColumn('duration_weeks');
        });

        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropColumn('duration_weeks');
        });

        foreach (['plan_exercises', 'template_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['set_weights', 'progress_every_weeks', 'progress_increment_kg', 'day_label']);
            });
        }
    }
};
