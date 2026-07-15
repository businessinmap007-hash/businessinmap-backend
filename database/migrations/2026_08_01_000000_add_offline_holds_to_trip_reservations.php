<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline seat holds: let a carrier block capacity for seats it sold OUTSIDE
 * the app (a direct deal with a customer), so remaining_capacity reflects
 * reality and the app never oversells.
 *
 * An offline hold has no in-app counterparty, so client_id becomes nullable and
 * a `source` distinguishes app reservations from offline holds. Offline holds
 * never touch the rating ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->string('source', 16)->default('app')->after('currency'); // app | offline
        });

        // Offline holds have no in-app client. Keep the FK, just allow NULL.
        DB::statement('ALTER TABLE `trip_reservations` MODIFY `client_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Drop offline (client-less) holds before restoring the NOT NULL rule.
        DB::table('trip_reservations')->whereNull('client_id')->delete();
        DB::statement('ALTER TABLE `trip_reservations` MODIFY `client_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
