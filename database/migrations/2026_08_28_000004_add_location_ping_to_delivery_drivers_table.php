<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neither the freelance pool nor a business's own private driver had any live
 * position at all — `delivery_drivers` carried no lat/lng, and the job board
 * was never distance-filtered. The driver's own app pings this every 30-60s
 * WHILE it is carrying an active order (assigned/picked_up) — see
 * DeliveryDispatchService::pingLocation(). Idle drivers are not expected to
 * ping constantly; a stale/missing `location_updated_at` just means "no
 * position to show", not an error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_drivers', 'last_lat')) {
            Schema::table('delivery_drivers', function (Blueprint $table) {
                $table->decimal('last_lat', 10, 7)->nullable()->after('vehicle_label');
                $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
                $table->timestamp('location_updated_at')->nullable()->after('last_lng');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_drivers', 'last_lat')) {
            Schema::table('delivery_drivers', function (Blueprint $table) {
                $table->dropColumn(['last_lat', 'last_lng', 'location_updated_at']);
            });
        }
    }
};
