<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «المريض: لي / لشخص آخر» — the clinic booking board. The account that books stays `patient_id` (it is the one the
 * reminders, the agenda and the payments belong to); when the visit is for someone else the clinic also reads who
 * will actually walk in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->string('attendee_name', 120)->nullable()->after('reason');
            $table->string('attendee_phone', 30)->nullable()->after('attendee_name');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn(['attendee_name', 'attendee_phone']);
        });
    }
};
