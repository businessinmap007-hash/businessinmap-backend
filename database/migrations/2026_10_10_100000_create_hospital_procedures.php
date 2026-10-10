<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اضف للمستشفى إجراء طبي مثل العمليات الجراحية وما إلى ذلك» — المالك، 2026-10-10.
 *
 * Three tables, the same shape as the food and exercise libraries:
 *
 *  - `medical_procedures`  the platform's own list of surgeries, endoscopies and treatment procedures a hospital picks
 *                          from (`owner_id` null) — and the ones a hospital adds itself (`owner_id` = that hospital,
 *                          visible to it alone).
 *  - `hospital_procedures` what a hospital offers, with its OWN price (null = «السعر بعد التقييم»). The hospital prices
 *                          its procedures itself, like a centre prices its tests — not a service price list.
 *  - `procedure_requests`  a patient asks for one; the hospital accepts it with a date (and a quote when it had no
 *                          price), declines it, and marks it done. The name and price are kept as they were when asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_procedures', function (Blueprint $table) {
            $table->id();
            // surgery | endoscopy | procedure
            $table->string('kind', 20);
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'owner_id']);
        });

        Schema::create('hospital_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained('medical_procedures')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'procedure_id']);
        });

        Schema::create('procedure_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained('medical_procedures')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('name', 160);
            $table->decimal('price', 10, 2)->nullable();
            $table->date('preferred_date')->nullable();
            $table->text('notes')->nullable();
            // requested → accepted → completed; or declined / cancelled
            $table->string('status', 20)->default('requested');
            $table->timestamp('scheduled_at')->nullable();
            $table->text('hospital_note')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['hospital_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_requests');
        Schema::dropIfExists('hospital_procedures');
        Schema::dropIfExists('medical_procedures');
    }
};
