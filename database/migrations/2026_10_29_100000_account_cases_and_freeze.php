<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reported ACCOUNT is not removed (owner, 2026-10-29): it is frozen for three days, and the moderator opens a chat with it to
 * find out what the report is about, while the account keeps one screen — the moderators' chat.
 *
 *  - users.frozen_until          the account works again after this moment (null = never frozen)
 *  - account_cases               the moderator's file on one account: who opened it, why, and what came of it. Its chat is a
 *                                thread whose subject is the case (operation-chats/moderation/{case}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'frozen_until')) {
                $table->timestamp('frozen_until')->nullable();
            }
        });

        if (! Schema::hasTable('account_cases')) {
            Schema::create('account_cases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');           // the reported account
                $table->unsignedBigInteger('opened_by');         // the moderator dealing with it
                $table->string('status', 10)->default('open');   // open | closed
                $table->string('reason', 255)->nullable();       // what the reports said
                $table->timestamp('frozen_until')->nullable();
                $table->string('outcome', 12)->nullable();       // frozen | cleared
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'ac_user_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_cases');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('frozen_until'));
    }
};
