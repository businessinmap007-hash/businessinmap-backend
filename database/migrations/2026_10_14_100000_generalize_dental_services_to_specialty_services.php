<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «ابدأ بقائمة خدمات الجلدية والتجميل» — المالك، 2026-10-14.
 *
 * The dental clinics' list (2026-10-13) was built for ONE specialty; the dermatology and cosmetic clinics need the same
 * thing, and so will the next one. The tables become the general «خدمات التخصص»:
 *
 *  - `dental_services`        → `specialty_services`   (+ `specialty`: which specialty's list the row belongs to; the
 *                                                       `kind` says where inside it). Every existing row is dental.
 *  - `clinic_dental_services` → `clinic_specialty_services` (`dental_service_id` → `specialty_service_id`).
 *  - `clinic_appointments.dental_service_id` → `specialty_service_id`.
 *
 * Renames, not copies: every id (a clinic's offer, a booked appointment) stays exactly as it was. Each step checks what is
 * already done, so a run that stopped half way can simply be run again. Columns are renamed with CHANGE, which MySQL 5.7+,
 * MySQL 8 and MariaDB all take (RENAME COLUMN is not in MariaDB before 10.5.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dental_services') && ! Schema::hasTable('specialty_services')) {
            Schema::rename('dental_services', 'specialty_services');
        }

        if (! Schema::hasColumn('specialty_services', 'specialty')) {
            Schema::table('specialty_services', function (Blueprint $table) {
                $table->string('specialty', 20)->default('dental')->after('id');
                $table->index(['specialty', 'kind']);
            });
        }

        if (Schema::hasTable('clinic_dental_services') && ! Schema::hasTable('clinic_specialty_services')) {
            Schema::rename('clinic_dental_services', 'clinic_specialty_services');
        }

        if (Schema::hasColumn('clinic_specialty_services', 'dental_service_id')) {
            DB::statement('ALTER TABLE `clinic_specialty_services` CHANGE `dental_service_id` `specialty_service_id` BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('clinic_appointments', 'dental_service_id')) {
            DB::statement('ALTER TABLE `clinic_appointments` CHANGE `dental_service_id` `specialty_service_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinic_appointments', 'specialty_service_id')) {
            DB::statement('ALTER TABLE `clinic_appointments` CHANGE `specialty_service_id` `dental_service_id` BIGINT UNSIGNED NULL');
        }

        if (Schema::hasColumn('clinic_specialty_services', 'specialty_service_id')) {
            DB::statement('ALTER TABLE `clinic_specialty_services` CHANGE `specialty_service_id` `dental_service_id` BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasTable('clinic_specialty_services') && ! Schema::hasTable('clinic_dental_services')) {
            Schema::rename('clinic_specialty_services', 'clinic_dental_services');
        }

        if (Schema::hasColumn('specialty_services', 'specialty')) {
            // only the dental rows were there before: the other specialties' lists go with the column
            DB::table('specialty_services')->where('specialty', '!=', 'dental')->delete();

            Schema::table('specialty_services', function (Blueprint $table) {
                $table->dropIndex(['specialty', 'kind']);
                $table->dropColumn('specialty');
            });
        }

        if (Schema::hasTable('specialty_services') && ! Schema::hasTable('dental_services')) {
            Schema::rename('specialty_services', 'dental_services');
        }
    }
};
