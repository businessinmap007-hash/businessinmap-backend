<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «تصغير الصور وحذفها بعد التحميل» — المالك، 2026-10-12. A result PHOTO lives on the server only until it is safe on the
 * patient\'s phone: once the patient says he kept a copy (`patient_saved_at`) and the doctor who ordered the tests has
 * opened them (`doctor_seen_at`), the files are deleted after a short grace (`files_purged_at`). A photo nobody kept is
 * not deleted silently: the patient is warned (`expiry_warned_at`) and only then, after the retention window, it goes.
 * The TEXT of a result is never touched — it costs a few bytes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigation_orders', function (Blueprint $table) {
            $table->timestamp('patient_saved_at')->nullable()->after('ready_at');
            $table->timestamp('doctor_seen_at')->nullable()->after('patient_saved_at');
            $table->timestamp('expiry_warned_at')->nullable()->after('doctor_seen_at');
            $table->timestamp('files_purged_at')->nullable()->after('expiry_warned_at');
        });
    }

    public function down(): void
    {
        Schema::table('investigation_orders', function (Blueprint $table) {
            $table->dropColumn(['patient_saved_at', 'doctor_seen_at', 'expiry_warned_at', 'files_purged_at']);
        });
    }
};
