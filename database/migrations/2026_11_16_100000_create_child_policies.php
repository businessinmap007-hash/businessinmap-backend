<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «سياسة الأطفال» of a hotel (owner, 2026-10-27): children are priced by age bands the hotel writes — «حتى 5 مجانًا، من 6 إلى 11
 * نصف سعر الليلة» — counted in the room's capacity from an age it names, and a child's meals follow the same percentage. One policy per
 * business; it applies to every room it sells and to the rooms its partners sell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            // from this age a guest is an adult: counted, and inside the room's own price
            $table->unsignedTinyInteger('adult_from_age')->default(12);
            // from this age a child takes a place in the room's capacity
            $table->unsignedTinyInteger('counts_from_age')->default(6);
            // a child's meal plan costs the same percentage of the adult's
            $table->boolean('meals_follow')->default(true);
            // [{from, to, percent}] — percent of the night's price; 0 = free
            $table->json('bands');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_policies');
    }
};
