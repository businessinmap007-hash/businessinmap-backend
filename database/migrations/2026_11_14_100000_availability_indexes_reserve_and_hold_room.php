<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three things for a hotel that sells rooms to its own guests and to partners at the same moment:
 *
 *  - indexes under the questions every availability answer asks (rooms taken on a window, rooms an allocation sold on a night, the
 *    holds of a guest), so the answer stays quick as the bookings and allocations grow;
 *  - `bookable_items.reserve_rooms`: the rooms of a type the hotel keeps back for its own guests in peak — a partner can sell the type
 *    only up to its stock less this reserve (the owner: «غرفتان أو ثلاث احتياطي للضغط»);
 *  - `booking_holds.room_id`: the numbered room a guest is dealing with, so «تحت التعامل» names a room (1001) and not just a type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['bookable_id', 'starts_at', 'ends_at'], 'bookings_bookable_window_idx');
            $table->index(['allocation_id', 'starts_at', 'ends_at'], 'bookings_allocation_window_idx');
        });

        Schema::table('booking_holds', function (Blueprint $table) {
            $table->index(['user_id', 'expires_at'], 'booking_holds_user_expiry_idx');
            $table->unsignedBigInteger('room_id')->nullable()->after('bookable_item_id');
            $table->index(['room_id', 'expires_at'], 'booking_holds_room_expiry_idx');
        });

        Schema::table('allocation_release_requests', function (Blueprint $table) {
            $table->index(['allocation_id', 'status', 'starts_at'], 'release_requests_allocation_status_idx');
            $table->index(['status', 'expires_at'], 'release_requests_status_expiry_idx');
        });

        Schema::table('bookable_allocations', function (Blueprint $table) {
            $table->index(['partner_business_id', 'status'], 'idx_alloc_partner_status');
            $table->index(['bookable_item_id', 'status', 'allocation_type'], 'idx_alloc_item_status_type');
        });

        if (! Schema::hasColumn('bookable_items', 'reserve_rooms')) {
            Schema::table('bookable_items', function (Blueprint $table) {
                $table->unsignedInteger('reserve_rooms')->default(0)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropColumn('reserve_rooms');
        });

        Schema::table('bookable_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_alloc_partner_status');
            $table->dropIndex('idx_alloc_item_status_type');
        });

        Schema::table('allocation_release_requests', function (Blueprint $table) {
            $table->dropIndex('release_requests_allocation_status_idx');
            $table->dropIndex('release_requests_status_expiry_idx');
        });

        Schema::table('booking_holds', function (Blueprint $table) {
            $table->dropIndex('booking_holds_user_expiry_idx');
            $table->dropIndex('booking_holds_room_expiry_idx');
            $table->dropColumn('room_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_bookable_window_idx');
            $table->dropIndex('bookings_allocation_window_idx');
        });
    }
};
