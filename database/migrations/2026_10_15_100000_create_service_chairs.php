<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «كرسي وعليه الحلاق» — a salon with several chairs, each with its own person. A chair is a line of appointments of
 * its own: one customer at a time, its own queue. `bookings.chair_id` is the chair a booking sits on; `called_at` is
 * the moment the business called that turn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_chairs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name', 80)->nullable();
            $table->string('person_name', 120)->nullable();
            $table->string('photo')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('chair_id')->nullable()->index()->after('queue_turn');
            $table->timestamp('called_at')->nullable()->after('chair_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['chair_id', 'called_at']);
        });

        Schema::dropIfExists('service_chairs');
    }
};
