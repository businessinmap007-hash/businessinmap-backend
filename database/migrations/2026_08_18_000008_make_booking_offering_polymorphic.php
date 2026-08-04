<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A booking can now be ABOUT a listing, not only about a price row.
 *
 * `business_service_price_id` said which priced row a booking came from, which
 * covers a clinic («كشف عظام») but not the two cases that made menu_items a
 * priced surface in the first place: a furniture showroom and an estate agent
 * list «غرفة نوم — مودرن» and «شقة — غرفتين — سوبر لوكس» as menu_items, and a
 * viewing appointment is booked ON one of those.
 *
 * Worth being explicit, because the obvious reading is wrong: the listing's
 * price is NOT the booking's price. Two million pounds is what the flat costs,
 * not what the viewing costs. So this column says what the booking is ABOUT;
 * the amount keeps coming from the service's own price row, and the pricing
 * engine is untouched.
 *
 * The column it replaces shipped hours ago and holds nothing, so the backfill
 * is a formality rather than a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'offering_type')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('offering_type', 191)->nullable()->after('service_id');
                $table->unsignedBigInteger('offering_id')->nullable()->after('offering_type');
                $table->index(['offering_type', 'offering_id'], 'bookings_offering_morph_index');
            });
        }

        if (Schema::hasColumn('bookings', 'business_service_price_id')) {
            DB::table('bookings')
                ->whereNotNull('business_service_price_id')
                ->update([
                    'offering_type' => \App\Models\BusinessServicePrice::class,
                    'offering_id' => DB::raw('business_service_price_id'),
                ]);

            Schema::table('bookings', function (Blueprint $table) {
                $table->dropIndex('bookings_offering_index');
                $table->dropColumn('business_service_price_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'business_service_price_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('business_service_price_id')->nullable()->after('service_id');
                $table->index('business_service_price_id', 'bookings_offering_index');
            });

            DB::table('bookings')
                ->where('offering_type', \App\Models\BusinessServicePrice::class)
                ->update(['business_service_price_id' => DB::raw('offering_id')]);
        }

        if (Schema::hasColumn('bookings', 'offering_type')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropIndex('bookings_offering_morph_index');
                $table->dropColumn(['offering_type', 'offering_id']);
            });
        }
    }
};
