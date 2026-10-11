<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «بالدور»: a business that books by turn gives each customer the next number of the day instead of a chosen time.
 * `booking_flow` is the business's choice (NULL = by appointment, the pattern's own); `queue_turn` is the number a
 * booking was given. Both nullable: most businesses and most bookings have neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->string('booking_flow', 10)->nullable()->after('arrival_notice_text');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue_turn')->nullable()->after('course_group_id');
            $table->index(['business_id', 'queue_turn']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'queue_turn']);
            $table->dropColumn('queue_turn');
        });

        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->dropColumn('booking_flow');
        });
    }
};
