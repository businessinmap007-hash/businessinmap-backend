<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اضافه للطبيب ايضا التشخيص وحالة المريض وفى الروشتة مدة العلاج كام يوم او
 * اسبوع او شهر» — المالك، 2026-08-27. `diagnosis` already existed on
 * `prescriptions`; `patient_condition` is the new companion field — the
 * patient's general state at the visit, distinct from the diagnosis itself.
 *
 * `duration_days` already existed on `prescription_items` and drives
 * MedicationScheduleService unchanged (still always a day count, capped at
 * 30 there as a reminder-generation safety limit, not a prescription-data
 * limit). `duration_unit`/`duration_value` are input/display companions —
 * the doctor picks "٢ أسبوع", the controller converts to
 * duration_days = 14 and stores the original value+unit alongside it so the
 * prescription still reads back as weeks, not a day count nobody typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescriptions') && ! Schema::hasColumn('prescriptions', 'patient_condition')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->string('patient_condition', 500)->nullable()->after('diagnosis');
            });
        }

        if (Schema::hasTable('prescription_items') && ! Schema::hasColumn('prescription_items', 'duration_unit')) {
            Schema::table('prescription_items', function (Blueprint $table) {
                // days | weeks | months — what the doctor actually picked.
                $table->string('duration_unit', 10)->nullable()->after('duration_days');
                $table->unsignedSmallInteger('duration_value')->nullable()->after('duration_unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'patient_condition')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropColumn('patient_condition');
            });
        }

        if (Schema::hasTable('prescription_items') && Schema::hasColumn('prescription_items', 'duration_unit')) {
            Schema::table('prescription_items', function (Blueprint $table) {
                $table->dropColumn(['duration_unit', 'duration_value']);
            });
        }
    }
};
