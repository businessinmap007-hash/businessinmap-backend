<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «تحت التعامل» (owner, 2026-10-21): while one party is filling in a booking for a room, the room is held for them for a few minutes
 * and every other party — the hotel's own guest, a travel company's guest — sees it as being dealt with by someone else. The hold turns
 * into the booking when it is made, and falls away on its own when the time is up, so a room is never offered to two people at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_holds')) {
            Schema::create('booking_holds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bookable_item_id');
                $table->unsignedBigInteger('user_id');                    // who is booking
                $table->unsignedBigInteger('seller_business_id')->nullable(); // the business the booking is made at (a partner, or the hotel itself)
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->dateTime('expires_at');
                $table->timestamps();

                $table->index(['bookable_item_id', 'expires_at']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
