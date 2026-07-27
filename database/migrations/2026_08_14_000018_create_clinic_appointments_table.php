<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A patient's appointment at a clinic. The clinic is a business account, the
 * patient a client. A patient requests a time (status=requested) and the clinic
 * confirms/reschedules/rejects it, or the clinic books one directly (confirmed).
 * The clinic never double-books: a confirmed appointment blocks an overlapping
 * one. Readable only by the two parties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->index();   // the clinic (business)
            $table->unsignedBigInteger('patient_id')->index();  // the client
            $table->unsignedBigInteger('created_by')->nullable(); // who booked it

            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);

            // requested | confirmed | completed | cancelled | no_show
            $table->string('status', 20)->default('requested')->index();

            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointments');
    }
};
