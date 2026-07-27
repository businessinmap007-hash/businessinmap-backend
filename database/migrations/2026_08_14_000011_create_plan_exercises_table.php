<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One workout line on a training plan: the exercise, which day it falls on, and
 * the sets/reps/rest prescription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_plan_id')->index();

            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sun..6=Sat, null = any day
            $table->string('name');
            $table->unsignedSmallInteger('sets')->nullable();
            $table->string('reps', 40)->nullable();   // "12" or "10-12"
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->string('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_exercises');
    }
};
