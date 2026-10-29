<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «أقصى عدد أطفال» per room type (owner, 2026-10-29): the way rate sheets and booking sites write a room — «2 بالغ + 2 طفل». The capacity
 * stays the adults; this is how many children (two and older — a baby sleeps in a cot, which has its own limit) a room takes besides,
 * times the rooms booked. Null = the hotel has not said, no limit beyond the capacity rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_children')->nullable()->after('single_occupancy_percent');
        });
    }

    public function down(): void
    {
        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropColumn('max_children');
        });
    }
};
