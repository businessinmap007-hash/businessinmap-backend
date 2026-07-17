<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIM-15.1 — account deletion, and the ban flag it must respect.
 *
 * Deletion is a two-step process, not an event. `deleted_at` (already on the
 * table) marks the account gone from the user's point of view on day 0; the
 * money does NOT move then. Only after the grace window does the balance
 * escheat to the treasury and the identity get scrubbed — so a restore inside
 * the window returns the account AND the balance exactly as they were.
 *
 *   deletion_requested_at  — when the user asked. Distinct from deleted_at so an
 *                            admin soft-delete is never mistaken for a request.
 *   deletion_scheduled_at  — when finalization becomes due (requested + grace).
 *                            Stored, not computed, so changing the config later
 *                            cannot retroactively move a pending account's date.
 *   anonymized_at          — finalization ran and the PII is gone. Irreversible.
 *   deletion_hold_reason   — finalization refused to seize (money still locked,
 *                            or a dispute appeared after the request). The row
 *                            waits for a human instead of being swept.
 *
 * banned_at / ban_reason are the account-level ban the fines system will set
 * later. The column lands now because deletion must refuse a banned account:
 * anonymization erases the email and phone a ban is enforced on, so "delete and
 * re-register" would otherwise be a one-click way to clear a permanent ban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('deleted_at');
            $table->timestamp('deletion_scheduled_at')->nullable()->after('deletion_requested_at');
            $table->timestamp('anonymized_at')->nullable()->after('deletion_scheduled_at');
            $table->text('deletion_hold_reason')->nullable()->after('anonymized_at');

            $table->timestamp('banned_at')->nullable()->after('deletion_hold_reason');
            $table->string('ban_reason')->nullable()->after('banned_at');

            // The sweep's only query: due, not yet finalized.
            $table->index(['deletion_scheduled_at', 'anonymized_at'], 'users_deletion_due_idx');
            $table->index('banned_at', 'users_banned_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_deletion_due_idx');
            $table->dropIndex('users_banned_idx');
            $table->dropColumn([
                'deletion_requested_at',
                'deletion_scheduled_at',
                'anonymized_at',
                'deletion_hold_reason',
                'banned_at',
                'ban_reason',
            ]);
        });
    }
};
