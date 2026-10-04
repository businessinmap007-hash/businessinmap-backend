<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «احذف الحقول الحساسة فقط وابقِ الأصناف» — المالك، 2026-10-05. A finished prescription's diagnosis, patient
 * condition and notes leave the server once the PATIENT'S PHONE has confirmed it holds the exact copy:
 *   archived_by_patient_at — the phone proved it holds the full content (its fingerprint matches);
 *   content_purged_at      — when the sensitive fields were removed here. The fingerprint and the medicine lines stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('archived_by_patient_at')->nullable()->after('content_hash');
            $table->timestamp('content_purged_at')->nullable()->after('archived_by_patient_at');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['archived_by_patient_at', 'content_purged_at']);
        });
    }
};
