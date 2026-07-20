<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent to the conduct rules, and the record of breaking them.
 *
 * The version is stored alongside the timestamp because a charter that is
 * rewritten is a different promise. Someone who agreed to the old wording has
 * not agreed to the new one, and "they accepted" with no idea WHAT they
 * accepted is worthless the first time it is challenged.
 *
 * Violations are their own table rather than a counter, because the arbitrator
 * needs to point at the message: a mark saying "was rude twice" cannot be
 * argued with, and a ruling that a party cannot argue with is not a ruling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thread_participants', function (Blueprint $table) {
            $table->timestamp('conduct_accepted_at')->nullable()->after('joined_at');
            $table->unsignedSmallInteger('conduct_version')->nullable()->after('conduct_accepted_at');
        });

        Schema::create('conduct_violations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();

            // The offending message, when there is one to point at. Nullable
            // because conduct is not only what was typed.
            $table->foreignId('thread_message_id')->nullable()->constrained('thread_messages')->nullOnDelete();

            $table->unsignedBigInteger('against_user_id');
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->text('reason');

            $table->timestamps();

            $table->index(['thread_id', 'against_user_id'], 'conduct_violations_thread_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conduct_violations');

        Schema::table('thread_participants', function (Blueprint $table) {
            $table->dropColumn(['conduct_accepted_at', 'conduct_version']);
        });
    }
};
