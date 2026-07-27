<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's progress against a training plan: a dated check-in with their
 * weight and a note, so the trainer can follow how the plan is going.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_plan_id')->index();
            $table->unsignedBigInteger('client_id')->index(); // who logged it (the trainee)

            $table->date('logged_on');
            $table->decimal('weight', 6, 2)->nullable(); // kg
            $table->string('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_progress_logs');
    }
};
