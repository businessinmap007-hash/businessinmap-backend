<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اكتمل العدد اليوم»: an OPTIONAL button of a business (a doctor may rather add working hours when there are more
 * patients). It closes new bookings for one date and can be reopened; nothing closes by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('booking_closed_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('booking_closed_date');
        });
    }
};
