<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence a party (or the arbitrator) attaches to a room message.
 *
 * The arbitration room could only ever hold text, so the person deciding a
 * case never saw the photo of the item, the receipt, or the screenshot the
 * argument turned on. A message can now carry files; each is one row here.
 *
 * Hung off `thread_messages`, not `disputes`, on purpose — attachments are a
 * property of a message in ANY thread, matching why `threads` was built
 * generic. Cascades with its message (and so with the whole room on a
 * consented purge); the file on disk is unlinked separately since the DB
 * cascade cannot reach the filesystem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_message_id')->constrained('thread_messages')->cascadeOnDelete();

            // Public-relative path, e.g. "files/uploads/1690_ab_receipt.jpg" —
            // never absolute, so it survives a host change (see ImageUploadService).
            $table->string('path');

            // The sanitised original name, shown to a human; the stored file is
            // renamed, so this is the only recognisable label.
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();

            $table->timestamps();

            $table->index('thread_message_id', 'thread_message_attachments_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_message_attachments');
    }
};
