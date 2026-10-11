<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «يجب التواجد قبل الموعد بـ ١٥ دقيقة» — what the business asks of whoever it books, in its own numbers and words.
 * Both nullable: NULL is «nothing to say», and most businesses will have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('arrival_notice_minutes')->nullable()->after('lead_time_minutes');
            $table->string('arrival_notice_text', 255)->nullable()->after('arrival_notice_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->dropColumn(['arrival_notice_minutes', 'arrival_notice_text']);
        });
    }
};
