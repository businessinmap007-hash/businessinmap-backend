<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Both parties agreeing to delete the conversation of a finished dispute.
 *
 * The standing rule is that dispute records are never deleted, because they are
 * evidence. This is the one consented exception: once a case is closed, BOTH
 * parties may agree that there is nothing left to appeal and ask for the
 * conversation to be erased. Only the conversation goes — the dispute number,
 * the two parties, and the ruling stay, so what happened is still on record;
 * what disappears is the back-and-forth.
 *
 * Two confirmation columns, not one flag, for the same reason the settlement
 * has two: deletion is irreversible, so it must take both hands, and the gap
 * between the first and the second is a real state the other party is shown.
 *
 * `room_purged_at` is what stops the conversation being silently recreated:
 * the thread is built on demand, so without a marker the next page view would
 * make a fresh empty one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->timestamp('client_purge_confirmed_at')->nullable()->after('closed_reason');
            $table->timestamp('business_purge_confirmed_at')->nullable()->after('client_purge_confirmed_at');
            $table->timestamp('room_purged_at')->nullable()->after('business_purge_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn([
                'client_purge_confirmed_at',
                'business_purge_confirmed_at',
                'room_purged_at',
            ]);
        });
    }
};
