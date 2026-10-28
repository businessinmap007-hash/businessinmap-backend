<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The terms of a hotel's rate sheet the platform lacked (owner, 2026-10-28 «نفذ النقاط الناقصة كلها»):
 *
 *  - single_occupancy_percent (per room type): one adult alone in a room for two pays this % of the night (Egyptian rate sheets
 *    list a lower single rate per room type — 4700 single against 5400 double is 87%). Null = he pays the whole room.
 *  - third_adult_reduction: an adult in an extra bed pays one person's night less this % («تخفيض الشخص الثالث 30%»), instead of the
 *    fixed extra-bed price.
 *  - cots_max / cot_price: a baby cot on request, per room, at a price a night (0 = free).
 *  - non_refundable_percent: the cheaper «غير قابل للاسترداد» rate — this % off; null = the hotel sells only the flexible rate.
 *  - hotel_seasons: a hotel's seasons — the rooms' price in it (a % or an amount on every room type, or a price per type), the meal
 *    plans' price in it, and the shortest stay that may ARRIVE in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->decimal('single_occupancy_percent', 5, 2)->nullable()->after('capacity');
        });

        Schema::table('child_policies', function (Blueprint $table) {
            $table->decimal('third_adult_reduction', 5, 2)->nullable()->after('extra_beds_max');
            $table->unsignedTinyInteger('cots_max')->default(0)->after('single_parent_percent');
            $table->decimal('cot_price', 10, 2)->default(0)->after('cots_max');
        });

        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->decimal('non_refundable_percent', 5, 2)->nullable()->after('min_nights');
        });

        Schema::create('hotel_seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('room_change_type', 10)->nullable(); // percent | amount
            $table->decimal('room_change_value', 10, 2)->nullable();
            $table->json('room_prices')->nullable(); // {bookable_item_id: night price}
            $table->json('meal_prices')->nullable(); // {option_id: price}
            $table->unsignedTinyInteger('min_nights')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_seasons');

        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->dropColumn('non_refundable_percent');
        });

        Schema::table('child_policies', function (Blueprint $table) {
            $table->dropColumn(['third_adult_reduction', 'cots_max', 'cot_price']);
        });

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropColumn('single_occupancy_percent');
        });
    }
};
