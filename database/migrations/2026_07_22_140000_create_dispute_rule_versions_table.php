<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dispute-room rules, as versions rather than an editable document.
 *
 * Editing in place would silently rewrite what people already agreed to: every
 * acceptance stores a version number precisely so that "they accepted" can be
 * answered with WHAT they accepted. So publishing never mutates a past version
 * — it adds the next one, and every acceptance of an older number stops
 * counting until that party accepts again.
 *
 * That is also why the text lives here rather than in a settings row: a settings
 * value has one current state and no history, and the history is the point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_rule_versions', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('version')->unique();
            $table->string('title');

            // [{title, clauses: [...]}, ...] — the shape the app renders and the
            // charter endpoint returns.
            $table->json('sections');

            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at', 'dispute_rule_versions_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_rule_versions');
    }
};
