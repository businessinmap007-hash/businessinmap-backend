<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الطبيب الموظف فى المستشفى» — المالك، 2026-10-14: a physician who works FOR a hospital (a staff member of its account)
 * is recorded on what he does, instead of the hospital standing as the only name:
 *   - business_staff.is_physician     the owner marks which staff members are physicians;
 *   - clinic_appointment_slots.physician_id  a physician publishes his own times (two physicians may sit at the same
 *                                     minute, so the unique key now includes him);
 *   - clinic_appointments / prescriptions / investigation_orders .physician_id  who saw, wrote, ordered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('business_staff', 'is_physician')) {
            Schema::table('business_staff', function (Blueprint $t) {
                $t->boolean('is_physician')->default(false)->after('is_active');
            });
        }

        if (! Schema::hasColumn('clinic_appointment_slots', 'physician_id')) {
            Schema::table('clinic_appointment_slots', function (Blueprint $t) {
                $t->unsignedBigInteger('physician_id')->nullable()->after('clinic_id')->index();
            });

            Schema::table('clinic_appointment_slots', function (Blueprint $t) {
                $t->dropUnique('clinic_appointment_slots_clinic_id_starts_at_unique');
                $t->unique(['clinic_id', 'physician_id', 'starts_at'], 'clinic_slots_clinic_physician_starts_unique');
            });
        }

        foreach (['clinic_appointments', 'prescriptions', 'investigation_orders'] as $table) {
            if (! Schema::hasColumn($table, 'physician_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('physician_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['clinic_appointments', 'prescriptions', 'investigation_orders'] as $table) {
            if (Schema::hasColumn($table, 'physician_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('physician_id'));
            }
        }

        if (Schema::hasColumn('clinic_appointment_slots', 'physician_id')) {
            Schema::table('clinic_appointment_slots', function (Blueprint $t) {
                $t->dropUnique('clinic_slots_clinic_physician_starts_unique');
            });
            Schema::table('clinic_appointment_slots', function (Blueprint $t) {
                $t->dropColumn('physician_id');
                $t->unique(['clinic_id', 'starts_at'], 'clinic_appointment_slots_clinic_id_starts_at_unique');
            });
        }

        if (Schema::hasColumn('business_staff', 'is_physician')) {
            Schema::table('business_staff', fn (Blueprint $t) => $t->dropColumn('is_physician'));
        }
    }
};
