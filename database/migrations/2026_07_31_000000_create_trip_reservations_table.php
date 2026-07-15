<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's reservation of capacity on a published trip leg — the piece that
 * turns the scheduling directory into a working service.
 *
 * A parcel sender / passenger reserves `units` of a leg's capacity; the carrier
 * confirms; on completion the operation is ledgered into the universal rating
 * for BOTH parties (so a carrier's — e.g. a limousine captain's — reputation is
 * built from real trips). Active reservations (pending/confirmed/completed)
 * consume the leg's capacity; cancelling releases it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_schedule_id')->constrained('trip_schedules')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete(); // the carrier
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();   // the sender/passenger

            $table->unsignedInteger('units')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable(); // price snapshot at reservation time
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('currency', 10)->default('EGP');

            // pending -> confirmed -> completed | cancelled
            $table->string('status', 16)->default('pending');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['trip_schedule_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_reservations');
    }
};
