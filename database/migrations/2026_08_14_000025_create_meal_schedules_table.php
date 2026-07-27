<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A patient's usual meal times. Medication doses that are tied to food (before /
 * with / after a meal) are scheduled off these; a patient with none set falls
 * back to sensible defaults (08:00 / 14:00 / 20:00).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->time('breakfast_at')->default('08:00:00');
            $table->time('lunch_at')->default('14:00:00');
            $table->time('dinner_at')->default('20:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_schedules');
    }
};
