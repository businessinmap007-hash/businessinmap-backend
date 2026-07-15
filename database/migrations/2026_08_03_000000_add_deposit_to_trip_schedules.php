<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional refundable deposit on a trip leg (commitment hold). When a leg sets
 * `deposit_per_unit`, reserving holds `deposit_per_unit * units` from the
 * client's wallet (balance → locked); it is released back to the client on
 * completion OR cancellation. `deposit_held` records what was actually held so
 * release is exact and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table) {
            $table->decimal('deposit_per_unit', 12, 2)->nullable()->after('price');
        });

        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->decimal('deposit_held', 12, 2)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table) {
            $table->dropColumn('deposit_per_unit');
        });

        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->dropColumn('deposit_held');
        });
    }
};
