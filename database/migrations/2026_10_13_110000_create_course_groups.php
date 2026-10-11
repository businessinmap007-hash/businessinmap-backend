<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «كورس» — a course is a priced row of the business (`offering_id`); a GROUP is one run of it: when it starts, when
 * it meets, how many seats. A customer joins a group, and the booking it makes is the enrolment (`course_group_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            // the course: a `business_service_prices` row of this business
            $table->unsignedBigInteger('offering_id')->index();
            $table->string('name', 120);
            $table->string('level', 40)->nullable();
            // «الأحد والثلاثاء · 7 م» — written by the business, read as it is
            $table->string('schedule_text', 160)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('seats')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('course_group_id')->nullable()->index()->after('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('course_group_id');
        });

        Schema::dropIfExists('course_groups');
    }
};
