<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «تأكيد الدفع من الطرفين حتى لا ينسى أو ينكر أي طرف» و«مع موعد الجلسة القادمة يُطلب القسط» — المالك، 2026-10-15.
 *
 *  - a payment the clinic recorded stays «بانتظار تأكيد المريض» until the patient confirms it in the app, or disputes it
 *    with a note; a payment the patient confirmed can no longer be taken back by the clinic;
 *  - `clinic_appointments.installment_amount`: what the clinic will ask at the NEXT session (null = what is left).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointment_payments', function (Blueprint $table) {
            $table->timestamp('patient_confirmed_at')->nullable()->after('paid_at');
            $table->timestamp('disputed_at')->nullable()->after('patient_confirmed_at');
            $table->string('dispute_note', 255)->nullable()->after('disputed_at');
        });

        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->decimal('installment_amount', 10, 2)->nullable()->after('visit_fee');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn('installment_amount');
        });

        Schema::table('clinic_appointment_payments', function (Blueprint $table) {
            $table->dropColumn(['patient_confirmed_at', 'disputed_at', 'dispute_note']);
        });
    }
};
