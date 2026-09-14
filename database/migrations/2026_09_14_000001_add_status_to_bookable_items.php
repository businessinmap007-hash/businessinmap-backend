<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manual override the merchant sets — "available" or "maintenance" (a room
 * being repainted, say, closed to new bookings until the merchant reopens
 * it). "Currently booked" is never stored here: it's derived from live
 * bookings overlapping now, the same definition BookableAvailabilityService
 * already uses to block a slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookable_items', 'status')) {
            return;
        }

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
