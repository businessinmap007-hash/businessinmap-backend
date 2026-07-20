<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversation with a participant list rather than two user columns.
 *
 * The existing `conversations`/`messages` tables are strictly 1-to-1
 * (user_one_id/user_two_id, sender_id/receiver_id) and cannot hold a third
 * party, which is exactly what an arbitrated dispute needs. Both are empty and
 * unreferenced by any controller or route, so this replaces the shape rather
 * than extending it.
 *
 * Deliberately generic (`threads`, not `dispute_rooms`): a dispute room is the
 * first case, not the only one. Building a dispute-only messaging system would
 * guarantee a second messaging system the day general chat is wanted — the
 * duplicate-subsystem mistake this codebase has already paid for once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();

            // What the conversation is ABOUT. Nullable so a plain
            // person-to-person thread needs no subject at all.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->enum('status', ['open', 'locked'])->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'threads_subject_idx');
            $table->index('last_message_at', 'threads_last_message_idx');
        });

        Schema::create('thread_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');

            // Why this person is in the room. The arbitrator is the reason this
            // table exists at all.
            $table->enum('role', ['client', 'business', 'arbitrator', 'member'])->default('member');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            // One seat per person per thread.
            $table->unique(['thread_id', 'user_id'], 'thread_participants_unique');
            $table->index('user_id', 'thread_participants_user_idx');
        });

        Schema::create('thread_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();

            // Null for a system message: the platform narrating what happened
            // (opened, escalated, ruled) in the same stream the parties read.
            $table->unsignedBigInteger('sender_id')->nullable();

            $table->enum('kind', ['message', 'system'])->default('message');
            $table->text('body');
            $table->timestamps();

            $table->index(['thread_id', 'id'], 'thread_messages_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_messages');
        Schema::dropIfExists('thread_participants');
        Schema::dropIfExists('threads');
    }
};
