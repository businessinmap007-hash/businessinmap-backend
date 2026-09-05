<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One live execution of a published trip leg — the layer that turns the
 * schedule directory into an actual vehicle on the road. Started by the
 * owner or a staff member delegated the `schedules` capability (no separate
 * driver login). `passenger_count` is set at start for passenger/limousine
 * legs — everyone is treated as completing together at the final stop (no
 * per-passenger drop-off), so there is nothing further to reconcile there.
 * A freight/distribution run instead carries `trip_run_manifest_items` and
 * passes through `awaiting_reconciliation` before `completed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_schedule_id')->constrained('trip_schedules')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();

            // in_progress -> awaiting_reconciliation (freight/distribution only) -> completed
            $table->string('status', 24)->default('in_progress')->index();
            $table->unsignedInteger('passenger_count')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_runs');
    }
};
