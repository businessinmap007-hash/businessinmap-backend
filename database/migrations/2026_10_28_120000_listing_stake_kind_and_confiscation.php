<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What stands behind an owner listing, and what happens to it when a moderator takes the listing down (owner, 2026-10-28):
 * the guarantee is FROZEN for the whole life of the listing, or a deposit is held; if a moderator removes the listing for a
 * violation, that stake is confiscated.
 *
 *  - owner_listings.deposit_kind     wallet | guarantee — what the stake is (deposit_amount is its size)
 *  - owner_listings.deposit_state    held | released | forfeited
 *  - content_reports.violation       the rule broken, as the moderator named it when removing
 *  - content_reports.confiscated_amount   what was taken from the owner for it (0 when nothing was)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_listings', 'deposit_kind')) {
                $table->string('deposit_kind', 10)->nullable()->after('deposit_amount');
            }
        });

        Schema::table('content_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('content_reports', 'violation')) {
                $table->string('violation', 30)->nullable()->after('action');
                $table->decimal('confiscated_amount', 10, 2)->default(0)->after('violation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owner_listings', fn (Blueprint $t) => $t->dropColumn('deposit_kind'));
        Schema::table('content_reports', fn (Blueprint $t) => $t->dropColumn(['violation', 'confiscated_amount']));
    }
};
