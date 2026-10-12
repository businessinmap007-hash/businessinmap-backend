<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «أنشئ قائمة خدمات عيادات الأسنان» — المالك، 2026-10-13.
 *
 * Two tables, the same shape as the medical procedures (and the food / exercise libraries):
 *
 *  - `dental_services`        the platform's own list of what a dental clinic does — a cleaning, a filling, a root canal,
 *                             an implant, braces… (`owner_id` null) — and the ones a clinic adds itself (`owner_id` =
 *                             that clinic, visible to it alone).
 *  - `clinic_dental_services` what a clinic offers, with its OWN price (null = «السعر بعد الكشف»).
 *
 * Why own tables and not an option group: a clinic is ONE child («عيادة») whatever its specialty, and the specialty is a
 * descriptive option. A line group linked to that child would be offered to every doctor's clinic; this list is shown
 * only to an account that ticked «أسنان» (see DentalServicesService::isDentalClinic).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_services', function (Blueprint $table) {
            $table->id();
            // checkup | preventive | restorative | endodontic | periodontal | surgical | prosthetic | orthodontic | cosmetic | pediatric
            $table->string('kind', 20);
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'owner_id']);
        });

        Schema::create('clinic_dental_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dental_service_id')->constrained('dental_services')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'dental_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_dental_services');
        Schema::dropIfExists('dental_services');
    }
};
