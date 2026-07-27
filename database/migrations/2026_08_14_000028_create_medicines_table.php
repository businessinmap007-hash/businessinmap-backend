<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shared medicine dictionary. Doctors build it: every drug a doctor writes on
 * a prescription is captured here (name + strength), so the next doctor typing a
 * medicine sees what was written before and picks from it. Only a name and its
 * strength — no dosage, no instructions (those live on the prescription line).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // drug name
            $table->string('strength', 120)->nullable();  // e.g. 500mg
            $table->unsignedBigInteger('created_by')->nullable(); // the doctor who first wrote it
            $table->unsignedInteger('uses_count')->default(0);    // how often it's been written
            $table->timestamps();

            // One row per (name, strength); republishing the same drug is a no-op.
            $table->unique(['name', 'strength']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
