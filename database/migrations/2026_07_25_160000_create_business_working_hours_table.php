<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's weekly opening hours, one row per weekday.
 *
 * So a customer can filter search to shops that are open to order from RIGHT
 * NOW, and skip the closed ones. `day_of_week` is 0..6 = Sunday..Saturday to
 * match Carbon's dayOfWeek. A day may be marked closed, or carry an open/close
 * window; open > close means it runs past midnight (e.g. 20:00→02:00). A
 * business with NO rows is treated as "hours unknown" and is never hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday … 6=Saturday
            $table->boolean('is_closed')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'day_of_week'], 'business_working_hours_unique');
            $table->index(['day_of_week', 'business_id'], 'business_working_hours_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_working_hours');
    }
};
