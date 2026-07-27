<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's personal agenda: one unified timeline of everything that occupies or
 * reminds them of their time — clinic appointments, service bookings (e.g. a
 * restaurant), personal tasks they add themselves, and medication doses.
 *
 * "Blocking" items hold a span (starts_at..ends_at) and no two of them may
 * overlap for the same user — that is what stops booking a doctor and a
 * restaurant at the same minute. Non-blocking items (a medication dose, a
 * point reminder) sit at a single time and never clash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();

            // appointment | booking | personal | medication
            $table->string('kind', 20)->default('personal');
            $table->string('title');
            $table->text('notes')->nullable();

            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('blocking')->default(true);   // occupies time (clash check)

            // active | done | cancelled
            $table->string('status', 12)->default('active')->index();

            // The thing this item mirrors (a ClinicAppointment, Booking, PrescriptionItem…).
            $table->nullableMorphs('source');

            $table->boolean('remind')->default(false);    // push at starts_at
            $table->dateTime('reminded_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
