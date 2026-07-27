<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A training program a trainer (a gym/coach business) builds for a client: a
 * workout (plan_exercises) plus a nutrition plan (plan_meals), with the client's
 * progress logged over time (plan_progress_logs). Readable only by the two
 * parties (trainer, client).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_id')->index(); // the coach (business)
            $table->unsignedBigInteger('client_id')->index();  // the trainee

            $table->string('title');
            $table->string('goal')->nullable(); // e.g. weight loss, muscle gain

            // active | paused | completed | cancelled
            $table->string('status', 20)->default('active')->index();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plans');
    }
};
