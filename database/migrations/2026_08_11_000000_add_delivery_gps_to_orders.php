<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let checkout carry a one-off GPS delivery spot.
 *
 * A saved address (delivery_address_id) or a typed string already covered the
 * "deliver to my registered place" and "type an address" cases. This adds the
 * "I'm with friends, drop a pin here for this order only" case: the coordinates
 * ride on the order itself, resolved to a readable city line via our own
 * `cities` table (no map provider), and never touch the account's saved
 * addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_address_id');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng']);
        });
    }
};
