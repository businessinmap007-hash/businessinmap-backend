<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trainee's per-round completion ticks: the ONLY progress interaction in the
 * training section. After finishing each round (set) of an exercise, the trainee
 * confirms it — one row per (exercise, day, round). Deliberately no free text or
 * images here (a training section must never become a channel for photos between
 * a trainee and a trainer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_exercise_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_exercise_id')->index();
            $table->unsignedBigInteger('training_plan_id')->index();
            $table->unsignedBigInteger('client_id')->index();

            $table->date('for_date');
            $table->unsignedSmallInteger('round_number');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // A given round on a given day is confirmed once.
            $table->unique(['plan_exercise_id', 'for_date', 'round_number'], 'plan_exercise_round_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_exercise_rounds');
    }
};
