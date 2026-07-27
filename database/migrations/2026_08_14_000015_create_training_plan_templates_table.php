<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reusable training template a trainer builds once (no client) and later
 * applies to any client — the application COPIES the template's exercises and
 * meals into a fresh training_plan, so editing the template never touches a
 * plan already handed out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_id')->index(); // the coach (business)
            $table->string('title');
            $table->string('goal')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_templates');
    }
};
