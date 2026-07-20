<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation a ruling ORDERS, and a party's refusal of the terms.
 *
 * Ordered and paid are separate columns on purpose. The escrow moves the
 * instant a ruling lands because the platform is holding it, but compensation
 * comes out of a wallet that may be empty — and a ruling that silently fails to
 * execute is worse than one recorded as unpaid. Unpaid is also exactly the
 * `non_compliance` ground a fine already rests on, so the gap between the two
 * columns is the thing that makes that ground provable.
 *
 * The decline is stored rather than inferred from "hasn't accepted yet",
 * because refusing and not having opened the app are different facts about a
 * person and an arbitrator will weigh them differently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arbitration_sessions', function (Blueprint $table) {
            $table->decimal('compensation_amount', 12, 2)->default(0)->after('platform_fine_reason');
            $table->enum('compensation_to', ['client', 'business'])->nullable()->after('compensation_amount');
            $table->string('compensation_note', 500)->nullable()->after('compensation_to');
            $table->timestamp('compensation_paid_at')->nullable()->after('compensation_note');
        });

        Schema::table('thread_participants', function (Blueprint $table) {
            $table->timestamp('conduct_declined_at')->nullable()->after('conduct_version');
        });
    }

    public function down(): void
    {
        Schema::table('arbitration_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'compensation_amount', 'compensation_to', 'compensation_note', 'compensation_paid_at',
            ]);
        });

        Schema::table('thread_participants', function (Blueprint $table) {
            $table->dropColumn('conduct_declined_at');
        });
    }
};
