<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A meal line on a training template (mirrors plan_meals). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_plan_template_id')->index();

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
        Schema::dropIfExists('template_meals');
    }
};
