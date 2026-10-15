<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcels, round two (owner 2026-10-15): picked up from the sender by QR/code, handed over by QR/code, paid in cash at
 * pickup (sender) or at delivery (receiver) — recorded only, the wallet is for fees, deposits and guarantees — with an
 * approximate arrival the driver names and the real hour recorded; and a parcel rides in the run like a passenger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_parcels', function (Blueprint $table) {
            $table->unsignedBigInteger('trip_run_id')->nullable()->index()->after('trip_schedule_id');
            // who pays the driver, in cash: the sender when he gives the parcel, or the receiver when he gets it
            $table->string('payer', 12)->default('receiver')->after('seats');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expected_arrival_at')->nullable();
            // what the driver scans / types to take the parcel from the sender
            $table->text('pickup_code')->nullable();
            $table->string('pickup_code_hash', 64)->nullable();
            $table->unsignedTinyInteger('pickup_attempts')->default(0);
            $table->timestamp('picked_up_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_parcels', function (Blueprint $table) {
            $table->dropColumn([
                'trip_run_id', 'payer', 'paid_at', 'expected_arrival_at',
                'pickup_code', 'pickup_code_hash', 'pickup_attempts', 'picked_up_at',
            ]);
        });
    }
};
