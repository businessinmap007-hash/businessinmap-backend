<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «مشاركة الروشتة مع طبيب اخر» — المالك، 2026-08-27. Two owner decisions:
 *
 * 1. Either the patient OR the original doctor may share read access with a
 *    second doctor («الاثنين معا») — `prescription_shares` is a simple grant
 *    log, `shared_by_user_id` records which of the two actually did it.
 * 2. A shared-in doctor is read-only. Only the ORIGINAL doctor may amend a
 *    prescription, and amending never overwrites it — it creates a NEW
 *    prescription row (`revises_prescription_id` points back at the one it
 *    replaces) and cancels the old one, never deleting it
 *    («تحفظ نسخة جديدة وتختم القديمة ملغاة ولا تحذف»).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescriptions') && ! Schema::hasColumn('prescriptions', 'revises_prescription_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->foreignId('revises_prescription_id')->nullable()->after('appointment_id')
                    ->constrained('prescriptions')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('prescription_shares')) {
            Schema::create('prescription_shares', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
                $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['prescription_id', 'doctor_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_shares');

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'revises_prescription_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('revises_prescription_id');
            });
        }
    }
};
