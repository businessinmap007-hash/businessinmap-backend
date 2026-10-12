<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اجعل المريض يختار الخدمة عند الحجز» — المالك، 2026-10-13: a patient booking a dental clinic names what he came for
 * from the clinic's own list. The name and the price are kept as they were when he asked (a clinic may re-price or drop
 * the service later, and the appointment must still say what was booked), like a procedure request does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->foreignId('dental_service_id')->nullable()->after('service_price_id')
                ->constrained('dental_services')->nullOnDelete();
            $table->string('service_name', 160)->nullable()->after('dental_service_id');
            $table->decimal('service_price', 10, 2)->nullable()->after('service_name');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dental_service_id');
            $table->dropColumn(['service_name', 'service_price']);
        });
    }
};
