<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trips, round two (owner rules 2026-10-15):
 *  - an off-app passenger the carrier adds is NAMED, and stays out of ratings/counts until the fee is paid;
 *  - attendance needs both sides, the customer first and then the carrier;
 *  - a run started short of full needs the carrier's explicit acknowledgement;
 *  - parcels (شحنات) ride with the car: described by the sender, priced by the driver, handed over with a code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->string('passenger_name', 150)->nullable()->after('client_id');
            $table->string('passenger_phone', 40)->nullable()->after('passenger_name');
            $table->boolean('rating_visible')->default(false)->after('source');
            $table->decimal('rating_fee', 12, 2)->nullable()->after('rating_visible');
            $table->timestamp('client_confirmed_at')->nullable();
            $table->timestamp('carrier_confirmed_at')->nullable();
        });

        Schema::table('trip_runs', function (Blueprint $table) {
            $table->boolean('started_partial')->default(false)->after('passenger_count');
        });

        Schema::create('trip_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trip_schedule_id')->index();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('receiver_user_id')->nullable()->index();
            $table->string('receiver_name', 150)->nullable();
            $table->string('receiver_phone', 40)->nullable();
            $table->text('description');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedSmallInteger('seats')->default(0);
            $table->string('currency', 8)->default('EGP');
            // requested → priced → accepted → loaded → delivered (or rejected / cancelled)
            $table->string('status', 24)->default('requested')->index();
            // 4 digits + 2 letters the sender gives the receiver; the driver types it to hand the parcel over
            $table->text('delivery_code')->nullable();
            $table->string('delivery_code_hash', 64)->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->timestamp('loaded_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('delivered_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_parcels');

        Schema::table('trip_runs', function (Blueprint $table) {
            $table->dropColumn('started_partial');
        });

        Schema::table('trip_reservations', function (Blueprint $table) {
            $table->dropColumn(['passenger_name', 'passenger_phone', 'rating_visible', 'rating_fee', 'client_confirmed_at', 'carrier_confirmed_at']);
        });
    }
};
