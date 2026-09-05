<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A freight/distribution run's cargo list — a simple manual line (label +
 * quantity) typed in when the run starts, deliberately NOT tied to any
 * Order/inventory record for this first version. `delivered_qty`/
 * `returned_qty` are filled at reconciliation (run completion); what's still
 * "remaining in the vehicle" is computed as assigned - delivered - returned
 * rather than stored, so the three numbers can never disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_run_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_run_id')->constrained('trip_runs')->cascadeOnDelete();
            $table->string('label', 150);
            $table->string('unit', 24)->nullable();
            $table->unsignedInteger('assigned_qty');
            $table->unsignedInteger('delivered_qty')->nullable();
            $table->unsignedInteger('returned_qty')->nullable();
            $table->timestamps();

            $table->index('trip_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_run_manifest_items');
    }
};
