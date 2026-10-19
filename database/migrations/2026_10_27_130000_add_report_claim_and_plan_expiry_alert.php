<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two small follow-ups (owner, 2026-10-27):
 *  - a moderator who opens a report is recorded as the one dealing with it (`claimed_by`/`claimed_at` on the open reports of
 *    that item); no other moderator may act on it until he closes it (or lets it go);
 *  - a plan whose paid month is about to run out is announced once per month (`expiry_alerted_for` = the paid_until already
 *    announced).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('content_reports', 'claimed_by')) {
                $table->unsignedBigInteger('claimed_by')->nullable()->after('action');
                $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            }
        });

        Schema::table('training_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('training_plans', 'expiry_alerted_for')) {
                $table->date('expiry_alerted_for')->nullable()->after('renewals');
            }
        });
    }

    public function down(): void
    {
        Schema::table('content_reports', fn (Blueprint $t) => $t->dropColumn(['claimed_by', 'claimed_at']));
        Schema::table('training_plans', fn (Blueprint $t) => $t->dropColumn('expiry_alerted_for'));
    }
};
