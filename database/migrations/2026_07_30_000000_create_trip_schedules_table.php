<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling / routes service (a 6th typed platform service, key `schedules`).
 *
 * One row = one published "trip leg": a business declares it moves goods or
 * people FROM one governorate/city TO another, on a given day/date and time,
 * with capacity + price. Customers search by (origin, destination, date→day,
 * mode) and get every matching business ranked by trust (guarantee + rating).
 *
 * Serves four businesses off one entity: parcel/freight shipping, passenger
 * transport, private limousine, and factory/distributor dispatch. A limousine
 * "backhaul" return leg is just a row with is_return_leg + parent_trip_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();

            // freight | passenger | limousine | distribution
            $table->string('mode', 32)->index();

            // Route. City is optional; governorate is the searchable anchor.
            $table->unsignedBigInteger('origin_governorate_id')->index();
            $table->unsignedBigInteger('origin_city_id')->nullable();
            $table->unsignedBigInteger('destination_governorate_id')->index();
            $table->unsignedBigInteger('destination_city_id')->nullable();

            // weekly (recurring day_of_week) | one_off (trip_date) | on_demand
            $table->string('schedule_pattern', 16)->default('weekly');
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sun .. 6=Sat
            $table->date('trip_date')->nullable();
            $table->time('departure_time')->nullable();
            $table->time('return_time')->nullable();

            $table->unsignedInteger('capacity')->nullable();
            $table->string('capacity_unit', 24)->nullable(); // seat | parcel | kg | pallet
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->default('EGP');

            // Backhaul: a discounted return leg tied to its outbound parent.
            $table->boolean('is_return_leg')->default(false);
            $table->foreignId('parent_trip_id')->nullable()->constrained('trip_schedules')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            // The hot search path: route + day + status.
            $table->index(
                ['origin_governorate_id', 'destination_governorate_id', 'day_of_week', 'status'],
                'trip_schedules_search_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_schedules');
    }
};
