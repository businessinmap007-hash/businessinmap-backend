<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group chat, on the same threads system.
 *
 * A group is just a subjectless thread with more than two `member` seats — the
 * shape the participants table was always built for. Two properties set it
 * apart from a 1-to-1 DM: a `title` (its name) and a `created_by` (its owner,
 * who may rename it, add members, and delete it). Both are null on a DM,
 * dispute room, or operation chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->string('title')->nullable()->after('subject_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('title');
            $table->index('created_by', 'threads_created_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropIndex('threads_created_by_idx');
            $table->dropColumn(['title', 'created_by']);
        });
    }
};
