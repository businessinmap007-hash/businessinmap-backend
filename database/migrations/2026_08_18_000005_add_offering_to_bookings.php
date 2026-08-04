<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHICH priced row a booking was made from.
 *
 * A booking knew its business, its service and — when a named unit was
 * reserved — the unit. It did not know which price row the customer was
 * looking at, and while one item type could hold exactly one price that hardly
 * mattered: the resolver's ladder always landed on the same row.
 *
 * It matters now. A hospital may hold «كشف عظام 300» beside «كشف باطنة 250»,
 * both on the `category` item type, and the ladder would silently take the
 * newest. So the customer names the offering, the engine prices from that exact
 * row, and the booking keeps the reference — which is also what finally lets it
 * call itself «كشف — عظام» instead of «حجز #4127».
 *
 * Nullable, because every booking made before this — and every one for a
 * business with a single price — has nothing to name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'business_service_price_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('business_service_price_id')->nullable()->after('service_id');
            $table->index('business_service_price_id', 'bookings_offering_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'business_service_price_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_offering_index');
            $table->dropColumn('business_service_price_id');
        });
    }
};
