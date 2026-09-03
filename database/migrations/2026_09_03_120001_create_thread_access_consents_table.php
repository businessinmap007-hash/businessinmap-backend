<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a real participant (never the arbitrator — see role exclusion in
 * ThreadAccessGateService) has agreed to let admins/an arbitrator read a
 * thread's decrypted content. One row per (thread, user); re-answering
 * overwrites the previous decision — this is a standing preference, not a
 * one-time consent log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_access_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->enum('decision', ['approved', 'declined']);
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->unique(['thread_id', 'user_id'], 'thread_access_consents_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_access_consents');
    }
};
