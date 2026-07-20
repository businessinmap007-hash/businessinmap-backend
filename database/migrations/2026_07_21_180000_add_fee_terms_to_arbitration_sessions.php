<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the arbitrator charges, agreed before they hear the case.
 *
 * The session row used to be born at the ruling. It is now born when the
 * arbitrator ACCEPTS — which is what lets the fee be fixed in advance, and is
 * also more honest: a session that was accepted and never ruled on is a real
 * thing that should be visible in an arbitrator's record, not absent from it.
 *
 * `outcome` therefore becomes nullable: between acceptance and the ruling there
 * genuinely is no outcome yet, and writing a placeholder would make a
 * not-yet-decided case indistinguishable from a decided one.
 *
 * The fee is stored as BOTH its terms (fixed/percent + value) and the resulting
 * amount. The terms are what the parties were told; the amount is what those
 * terms came to against the escrow of the day. Keeping only one of them would
 * lose either the promise or the arithmetic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arbitration_sessions', function (Blueprint $table) {
            $table->enum('fee_type', ['fixed', 'percent'])->nullable()->after('outcome');
            $table->decimal('fee_value', 12, 2)->nullable()->after('fee_type');
            $table->decimal('fee_amount', 12, 2)->default(0)->after('fee_value');
            $table->enum('fee_on', ['client', 'business', 'split'])->nullable()->after('fee_amount');
            $table->timestamp('fee_terms_set_at')->nullable()->after('fee_on');
            $table->timestamp('accepted_at')->nullable()->after('fee_terms_set_at');
        });

        // Existing rows were all created by a ruling, so they were decided the
        // moment they existed.
        DB::statement('ALTER TABLE `arbitration_sessions` MODIFY `outcome` VARCHAR(40) NULL');
    }

    public function down(): void
    {
        Schema::table('arbitration_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'fee_type', 'fee_value', 'fee_amount', 'fee_on', 'fee_terms_set_at', 'accepted_at',
            ]);
        });

        DB::statement("UPDATE `arbitration_sessions` SET `outcome` = 'no_action' WHERE `outcome` IS NULL");
        DB::statement('ALTER TABLE `arbitration_sessions` MODIFY `outcome` VARCHAR(40) NOT NULL');
    }
};
