<?php

use App\Models\Dispute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two thread properties the operation chat needs.
 *
 * `retain_until` — when set, the conversation is kept until then and no longer
 * afterwards. An operation chat becomes deletable 7 days after the operation
 * completes; a dispute room leaves it null (disputes are kept, or purged only
 * by both parties' consent).
 *
 * `requires_conduct` — the conduct charter gate is a DISPUTE thing (you are
 * consenting that an arbitrator may rule against you), not something a plain
 * customer↔business chat should demand before you can type. It was previously
 * unconditional in ThreadService::post() because dispute rooms were the only
 * threads; it is now opt-in per thread. Existing dispute rooms are backfilled
 * to true so none silently loses its gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->timestamp('retain_until')->nullable()->after('last_message_at');
            $table->boolean('requires_conduct')->default(false)->after('status');
            $table->index('retain_until', 'threads_retain_until_idx');
        });

        // Every existing dispute room keeps its conduct gate.
        DB::table('threads')
            ->where('subject_type', (new Dispute())->getMorphClass())
            ->update(['requires_conduct' => true]);
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropIndex('threads_retain_until_idx');
            $table->dropColumn(['retain_until', 'requires_conduct']);
        });
    }
};
