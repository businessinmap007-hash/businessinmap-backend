<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A report on a CONVERSATION may carry the messages its reporter chose to show the moderators (owner, 2026-10-30): those, and only
 * those, are readable by the moderator at once — the rest of the chat opens only by the consent of both parties or by a quorum
 * of moderators, the same gate admins pass (ThreadAccessGateService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('content_reports', 'message_ids')) {
                $table->json('message_ids')->nullable()->after('details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('content_reports', fn (Blueprint $t) => $t->dropColumn('message_ids'));
    }
};
