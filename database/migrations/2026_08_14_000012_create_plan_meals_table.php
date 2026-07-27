<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One meal line on a training plan's nutrition side: what to eat, at which meal,
 * and its calories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_plan_id')->index();

            // breakfast | lunch | dinner | snack
            $table->string('meal_type', 20);
            $table->string('name');
            $table->unsignedInteger('calories')->nullable();
            $table->string('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_meals');
    }
};
