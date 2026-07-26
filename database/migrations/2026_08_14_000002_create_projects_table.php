<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own project management timeline — built for manufacturing and
 * construction: a furniture factory tracking a shipment through its build
 * stages, or a contractor finishing residential units. A project is owned by a
 * business (users) and broken into dated, dependent tasks (project_tasks).
 *
 * `operation_type`/`operation_id` is an OPTIONAL forward link to an order or
 * booking, so a future release can show the contracted customer read-only
 * progress; the MVP is a purely internal business tool and leaves it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();

            $table->string('title');
            $table->text('description')->nullable();

            // planning | active | on_hold | completed | cancelled
            $table->string('status', 20)->default('planning')->index();

            // A human reference the business recognises: a shipment number, a
            // unit/apartment code, a contract number.
            $table->string('reference', 120)->nullable();

            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();

            // A cached 0..100 rollup of task progress, recomputed on task change.
            $table->unsignedTinyInteger('progress')->default(0);

            // Optional link to the operation this project fulfils (order|booking).
            $table->string('operation_type')->nullable();
            $table->unsignedBigInteger('operation_id')->nullable();

            $table->timestamps();

            $table->index(['operation_type', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
