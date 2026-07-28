<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator presence sessions: a business (or a delegated operator user) marks
 * itself "online" watching a service screen. RealtimeNotificationService gates
 * realtime delivery on an active session (see BusinessOperatorSession::online).
 *
 * The model existed with no migration, so the table was missing and every
 * realtime-enabled dispatch threw a swallowed QueryException. This creates the
 * table the model already expects; the write path (heartbeat endpoints) and the
 * realtime transport itself remain future work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_operator_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service_type', 40)->nullable();
            $table->string('screen', 60)->nullable();
            $table->string('status', 12)->default(\App\Models\BusinessOperatorSession::STATUS_OFFLINE);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expected_until')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // The `online` scope filters status + ended_at + expected_until, scoped
            // by business_id or user_id — index both entry points.
            $table->index(['business_id', 'status', 'ended_at']);
            $table->index(['user_id', 'status', 'ended_at']);
            $table->index(['service_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_operator_sessions');
    }
};
