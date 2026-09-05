<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The published waypoint list on a trip leg — the template a live TripRun
 * snapshots into TripRunStop rows when a vehicle actually departs. No
 * lat/lng: a stop only needs a short label + a free-text address, since
 * navigation is delegated entirely to the phone's own Google Maps app via a
 * text-destination deep link (no map SDK, no routing API, no ongoing cost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_schedule_id')->constrained('trip_schedules')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('label', 120);
            $table->string('address', 255)->nullable();
            $table->timestamps();

            $table->index(['trip_schedule_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
