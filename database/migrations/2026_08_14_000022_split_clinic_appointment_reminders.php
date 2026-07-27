<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The patient now gets two reminders (one ~a day before, one ~2 hours before),
 * and the clinic none (the appointment is already on its calendar). Replaces the
 * single reminded_at marker with one per reminder so each fires exactly once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dateTime('reminded_day_at')->nullable()->after('notes');  // day-before sent
            $table->dateTime('reminded_soon_at')->nullable()->after('reminded_day_at'); // 2h-before sent
        });

        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dateTime('reminded_at')->nullable()->after('notes');
            $table->dropColumn(['reminded_day_at', 'reminded_soon_at']);
        });
    }
};
