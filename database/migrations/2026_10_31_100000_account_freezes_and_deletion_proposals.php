<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three guards on «the fifth freeze deletes the account» (owner, 2026-10-20):
 *
 *  1. only freezes of the last `bim.moderation.freeze_window_months` months count — `account_freezes` has one row per freeze,
 *     with the reason that was recorded for it;
 *  2. the deletion is not one moderator's decision: the fifth freeze files an `account_deletion_proposals` row and a panel of
 *     moderators (`account_deletion_votes`) must approve it;
 *  3. an approved deletion waits out a grace period during which the account may object in writing; an objection sends it back
 *     to the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_freezes')) {
            Schema::create('account_freezes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('case_id')->nullable();
                $table->unsignedBigInteger('frozen_by');
                $table->string('reason', 255)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'af_user_created');
            });

            // the freezes counted before this table existed: one row each, dated now, so none silently disappears
            DB::table('users')->where('freeze_count', '>', 0)->select(['id', 'freeze_count'])->orderBy('id')->each(function ($u) {
                for ($i = 0; $i < (int) $u->freeze_count; $i++) {
                    DB::table('account_freezes')->insert([
                        'user_id' => $u->id, 'frozen_by' => 0, 'reason' => 'قبل تسجيل الأسباب',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        }

        if (! Schema::hasTable('account_deletion_proposals')) {
            Schema::create('account_deletion_proposals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('case_id')->nullable();
                $table->unsignedBigInteger('proposed_by');
                $table->string('status', 10)->default('pending');   // pending | approved | executed | cancelled
                $table->unsignedSmallInteger('round')->default(1);  // 2 after an objection: the panel votes again
                $table->timestamp('execute_after')->nullable();     // set when the panel approves (the grace period)
                $table->text('objection')->nullable();
                $table->timestamp('objected_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'adp_user_status');
                $table->index(['status', 'execute_after'], 'adp_status_due');
            });
        }

        if (! Schema::hasTable('account_deletion_votes')) {
            Schema::create('account_deletion_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('proposal_id');
                $table->unsignedBigInteger('moderator_id');
                $table->unsignedSmallInteger('round')->default(1);
                $table->string('vote', 7);                          // approve | reject
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->unique(['proposal_id', 'moderator_id', 'round'], 'adv_one_vote');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_votes');
        Schema::dropIfExists('account_deletion_proposals');
        Schema::dropIfExists('account_freezes');
    }
};
