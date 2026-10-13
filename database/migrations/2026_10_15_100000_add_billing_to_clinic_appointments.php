<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «سعر الكشف أساسي يدفع عند الوصول، والخدمة تُدفع بعد التنفيذ وقابلة للزيادة أو النقصان، وقد تُقسَّم على دفعات» — المالك،
 * 2026-10-15.
 *
 *  - `clinic_appointments.visit_fee`        the fee of the visit kind, kept as it was when booked (paid on arrival).
 *  - `clinic_appointment_items.final_price` what the clinic charges for that service once it is done (null = as listed);
 *                                           the clinic may raise or lower it.
 *  - `clinic_appointment_payments`          every amount the clinic recorded as paid — one row per payment, so a treatment
 *                                           paid in instalments reads as it happened. The patient sees them; so does the
 *                                           doctor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->decimal('visit_fee', 10, 2)->nullable()->after('service_price_id');
        });

        // the fee of the visit kind each existing appointment was booked under, as it stands now
        DB::statement(
            'UPDATE clinic_appointments a JOIN business_service_prices p ON p.id = a.service_price_id '
            . 'SET a.visit_fee = p.price WHERE a.service_price_id IS NOT NULL AND a.visit_fee IS NULL'
        );

        Schema::table('clinic_appointment_items', function (Blueprint $table) {
            $table->decimal('final_price', 10, 2)->nullable()->after('price');
        });

        Schema::create('clinic_appointment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_appointment_id')->constrained('clinic_appointments')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            // visit_fee | service
            $table->string('kind', 20)->default('service');
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('clinic_appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointment_payments');

        Schema::table('clinic_appointment_items', function (Blueprint $table) {
            $table->dropColumn('final_price');
        });

        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn('visit_fee');
        });
    }
};
