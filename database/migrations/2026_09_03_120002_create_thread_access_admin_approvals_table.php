<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One admin's vote to view a thread the parties haven't (yet, or won't)
 * consent to. Unique per (thread, admin) so a single admin can't count
 * themself twice toward the configured quorum (chat_access_settings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_access_admin_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['thread_id', 'admin_id'], 'thread_access_admin_approvals_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_access_admin_approvals');
    }
};
