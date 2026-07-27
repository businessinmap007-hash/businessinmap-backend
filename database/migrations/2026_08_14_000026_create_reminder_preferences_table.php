<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user reminder lead times. Replaces the fixed "a day before / 2 hours
 * before" appointment reminders and the "at dose time" agenda reminders with
 * values each user can adjust. A user with no row falls back to the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();

            // Minutes before an appointment for the two reminders. The second is
            // nullable — null disables the closer reminder.
            $table->unsignedInteger('appointment_first_lead_minutes')->default(1440);  // 24h
            $table->unsignedInteger('appointment_second_lead_minutes')->nullable()->default(120); // 2h

            // Minutes before an agenda item (medication dose / task) to remind.
            $table->unsignedInteger('agenda_lead_minutes')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_preferences');
    }
};
