<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «عدد ورقم كل غرفة لدى الفندق فقط»: the rooms behind a room TYPE (a bookable item). The customer books the type;
        // the number is the hotel's own and appears only when the stay starts (and a deposit/guarantee is frozen).
        Schema::create('bookable_item_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bookable_item_id')->index();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('number', 40);
            // set by the hotel by hand — «مغلقة للصيانة»; occupancy is computed from live bookings, never written here
            $table->string('status', 20)->default('available');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['bookable_item_id', 'number']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // the room given to a stay when it starts; null for every other booking
            $table->unsignedBigInteger('room_id')->nullable()->after('bookable_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('room_id');
        });

        Schema::dropIfExists('bookable_item_rooms');
    }
};
