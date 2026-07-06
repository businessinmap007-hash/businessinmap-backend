<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for the unified invoice.
 *
 * An order can now be attached to a booking (dine-in: food ordered against a
 * table reservation) and carries a fulfillment type:
 *   delivery | dine_in | pickup
 *
 * When booking_id is set (dine_in), the order's food lines become part of the
 * booking's single invoice; otherwise it is a standalone order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'fulfillment_type')) {
                $table->string('fulfillment_type', 20)->default('delivery')->after('business_id');
            }
            if (! Schema::hasColumn('orders', 'booking_id')) {
                $table->foreignId('booking_id')
                    ->nullable()
                    ->after('fulfillment_type')
                    ->constrained('bookings')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'booking_id')) {
                $table->dropForeign(['booking_id']);
                $table->dropColumn('booking_id');
            }
            if (Schema::hasColumn('orders', 'fulfillment_type')) {
                $table->dropColumn('fulfillment_type');
            }
        });
    }
};
