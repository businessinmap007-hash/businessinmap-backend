<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الطبيب يطلب تحاليل وأشعة … والمريض يشاركها مع معمل مسجّل» — المالك، 2026-10-08.
 *
 * An investigation order is a doctor's list of lab tests and radiology exams for a patient, picked from the platform's
 * own lists (never typed). The patient shares it with a registered lab or radiology centre — or asks a centre directly
 * with no doctor (`doctor_id` null) — the centre accepts it (with a time), and attaches the results as photos.
 *
 * Three parties read it and nobody else: the doctor, the patient, the centre it was sent to. The items keep the name
 * and the centre's price as they were when the order was sent — an order must not change when a list or a price does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained('users')->nullOnDelete();
            // issued → sent → accepted → ready; or declined / cancelled
            $table->string('status', 20)->default('issued');
            $table->text('notes')->nullable();
            $table->text('center_note')->nullable();
            $table->timestamp('appointment_at')->nullable();
            // what the centre charges for the whole order — worked out when it is sent
            $table->decimal('total', 10, 2)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['center_id', 'status']);
            $table->index('doctor_id');
        });

        Schema::create('investigation_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_order_id')->constrained('investigation_orders')->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('options')->nullOnDelete();
            $table->string('kind', 12); // lab | radiology
            $table->string('name', 200);
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('investigation_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_order_items');
        Schema::dropIfExists('investigation_orders');
    }
};
