<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured dosage so a medicine line can be turned into reminders. The doctor
 * picks how often per day, its relation to food, which day-slots to take it in,
 * and for how many days. From these plus the patient's meal times, the doses are
 * scheduled onto the agenda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('frequency_per_day')->nullable()->after('instructions');
            // before | with | after  (relative to the meal)
            $table->string('food_timing', 10)->nullable()->after('frequency_per_day');
            // e.g. ["breakfast","lunch","dinner"] or ["morning","evening"]
            $table->json('time_slots')->nullable()->after('food_timing');
            $table->unsignedSmallInteger('duration_days')->nullable()->after('time_slots');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn(['frequency_per_day', 'food_timing', 'time_slots', 'duration_days']);
        });
    }
};
