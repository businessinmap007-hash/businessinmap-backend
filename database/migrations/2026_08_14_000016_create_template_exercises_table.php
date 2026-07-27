<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A workout line on a training template (mirrors plan_exercises). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_plan_template_id')->index();

            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->string('name');
            $table->unsignedSmallInteger('sets')->nullable();
            $table->string('reps', 40)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->string('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_exercises');
    }
};
