<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «استثنِ الروشتات المكتوبة من الحذف إلى أن نبني نسخة الطبيب» — المالك، 2026-10-05. The doctor loses the diagnosis
 * too when the server drops it, so a prescription is purged only once BOTH the patient's phone and the doctor's
 * phone confirmed holding the copy. Nothing sets this column yet (the doctor-side copy is unbuilt), so nothing is
 * purged until it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('archived_by_doctor_at')->nullable()->after('archived_by_patient_at');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('archived_by_doctor_at');
        });
    }
};
