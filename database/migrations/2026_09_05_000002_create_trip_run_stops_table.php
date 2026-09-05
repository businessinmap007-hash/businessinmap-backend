<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A snapshot of one TripStop for one live TripRun — copied in at start time
 * so editing the schedule's stop list later never rewrites history. Exactly
 * one row per run is ever `heading` or `arrived` at a time; the driver's one
 * action button reflects whichever one that is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_run_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_run_id')->constrained('trip_runs')->cascadeOnDelete();
            $table->foreignId('trip_stop_id')->nullable()->constrained('trip_stops')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('label', 120);
            $table->string('address', 255)->nullable();

            // pending -> heading -> arrived -> done
            $table->string('status', 16)->default('pending');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['trip_run_id', 'sequence']);
            $table->index(['trip_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_run_stops');
    }
};
