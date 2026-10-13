<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «اجعل الخدمات اختيار متعدد» — المالك، 2026-10-14.
 *
 * A visit may name several services (a filler and a laser session, a cleaning and a whitening). The one service the
 * appointment carried in three columns becomes rows: `clinic_appointment_items` — the service's id (null once the
 * service itself is gone) with its name and price KEPT as they were when asked, like a procedure request does.
 *
 * The three single columns are moved into rows first, then dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_appointment_id')->constrained('clinic_appointments')->cascadeOnDelete();
            $table->foreignId('specialty_service_id')->nullable()->constrained('specialty_services')->nullOnDelete();
            $table->string('name', 160);
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('clinic_appointment_id');
        });

        if (Schema::hasColumn('clinic_appointments', 'service_name')) {
            DB::statement(
                'INSERT INTO clinic_appointment_items (clinic_appointment_id, specialty_service_id, name, price, sort_order, created_at, updated_at) '
                . 'SELECT id, specialty_service_id, service_name, service_price, 0, NOW(), NOW() FROM clinic_appointments '
                . 'WHERE service_name IS NOT NULL OR specialty_service_id IS NOT NULL'
            );

            // the foreign key was named for the column's first name (dental_service_id): find it, do not guess
            $fk = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', 'clinic_appointments')
                ->where('COLUMN_NAME', 'specialty_service_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');

            if ($fk) {
                DB::statement('ALTER TABLE `clinic_appointments` DROP FOREIGN KEY `' . $fk . '`');
            }

            Schema::table('clinic_appointments', function (Blueprint $table) {
                $table->dropColumn(['specialty_service_id', 'service_name', 'service_price']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('specialty_service_id')->nullable()->after('service_price_id');
            $table->string('service_name', 160)->nullable()->after('specialty_service_id');
            $table->decimal('service_price', 10, 2)->nullable()->after('service_name');
        });

        // the first service of each visit goes back into the single columns
        DB::statement(
            'UPDATE clinic_appointments a JOIN ('
            . 'SELECT clinic_appointment_id, MIN(id) AS first_id FROM clinic_appointment_items GROUP BY clinic_appointment_id'
            . ') f ON f.clinic_appointment_id = a.id JOIN clinic_appointment_items i ON i.id = f.first_id '
            . 'SET a.specialty_service_id = i.specialty_service_id, a.service_name = i.name, a.service_price = i.price'
        );

        Schema::dropIfExists('clinic_appointment_items');
    }
};
