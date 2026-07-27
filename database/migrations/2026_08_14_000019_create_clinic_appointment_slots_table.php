<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open appointment slots a clinic publishes ahead of time. A patient books an
 * open future slot in one tap, which creates a confirmed clinic_appointment and
 * links it back here (appointment_id). A slot is "open" while appointment_id is
 * null and starts_at is still in the future.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->index();       // the clinic (business)
            $table->unsignedBigInteger('appointment_id')->nullable()->index(); // set once booked
            $table->unsignedBigInteger('created_by')->nullable();   // who published it

            $table->dateTime('starts_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);

            $table->timestamps();

            // A clinic never publishes the same start time twice.
            $table->unique(['clinic_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointment_slots');
    }
};
