<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stay's own check-in/check-out clock, separate from the business's
 * general working hours — a hotel can open its front desk at 9am and still
 * check guests in only from 3pm. NULL means the merchant hasn't set one yet,
 * same nullable-means-unset convention as every other column on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_booking_settings', 'check_in_time')) {
            return;
        }

        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->time('check_in_time')->nullable()->after('lead_time_minutes');
            $table->time('check_out_time')->nullable()->after('check_in_time');
        });
    }

    public function down(): void
    {
        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->dropColumn(['check_in_time', 'check_out_time']);
        });
    }
};
