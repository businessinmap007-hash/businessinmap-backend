<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which KIND of visit this is.
 *
 * `clinic_appointments.reason` is free text — a patient's sentence, not a
 * choice — so nothing in the diary knew whether a booking was a كشف or an
 * استشارة, and the per-kind duration added in the previous migration had
 * nothing to attach to.
 *
 * Pointing at the priced row answers three questions at once: what kind of
 * visit, how long it runs, and what it costs. Nullable throughout — clinics
 * that price nothing keep working exactly as before.
 *
 * `nullOnDelete`, not cascade: deleting a price must never delete a patient's
 * appointment. It loses the label, not the booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->foreignId('service_price_id')->nullable()->after('patient_id')
                ->constrained('business_service_prices')->nullOnDelete();
        });

        Schema::table('clinic_appointment_slots', function (Blueprint $table) {
            $table->foreignId('service_price_id')->nullable()->after('clinic_id')
                ->constrained('business_service_prices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointment_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_price_id');
        });

        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_price_id');
        });
    }
};
