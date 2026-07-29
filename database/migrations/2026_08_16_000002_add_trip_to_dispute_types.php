<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `trip` dispute type — a dispute over a schedules/trip reservation.
 * Like `order`, a trip reservation carries no escrow deposit, so it is neither
 * `booking` nor `deposit`. Companion to add_order_to_dispute_types; together
 * they let a dispute be opened on every ratable customer operation (booking,
 * order, trip) rather than bookings alone.
 */
return new class extends Migration
{
    private const WITH_TRIP = "enum('booking','deposit','external_deposit','wallet_hold','order','trip')";
    private const WITHOUT_TRIP = "enum('booking','deposit','external_deposit','wallet_hold','order')";

    public function up(): void
    {
        DB::statement('ALTER TABLE disputes MODIFY COLUMN `type` ' . self::WITH_TRIP . " NOT NULL DEFAULT 'booking'");
    }

    public function down(): void
    {
        DB::table('disputes')->where('type', 'trip')->update(['type' => 'deposit']);
        DB::statement('ALTER TABLE disputes MODIFY COLUMN `type` ' . self::WITHOUT_TRIP . " NOT NULL DEFAULT 'booking'");
    }
};
