<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «طلب تحرير غرفة» (owner, 2026-10-24): a room the hotel promised to a partner is not for sale to anyone else. When a guest of the
 * hotel wants it anyway, the partner is asked; if the partner agrees, the room goes back to the hotel's stock and is held for that guest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('allocation_release_requests')) {
            Schema::create('allocation_release_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bookable_item_id')->index();
                $table->unsignedBigInteger('requester_id')->index();          // the guest who wants the room
                $table->unsignedBigInteger('seller_business_id');             // the business the guest is booking at (the hotel)
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->string('status', 12)->default('pending');              // pending | approved | declined | expired
                $table->unsignedBigInteger('allocation_id')->nullable();      // the one that gave the room up
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->dateTime('decided_at')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_release_requests');
    }
};
