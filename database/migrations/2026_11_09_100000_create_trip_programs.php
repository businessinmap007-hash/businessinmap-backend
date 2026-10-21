<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «برنامج الرحلة» (owner, 2026-10-21): a tour company writes a trip as days and items, and drops into it the card of a room a hotel
 * agreed to give it (an allocation) — photos and what the room carries, at the company's own price, never the contract's.
 *
 * An item is a piece of text, a photo, or a room card. A room item points at the allocation, not at a copy of the room: when the hotel
 * stops the allocation the card goes with it and the item keeps its words.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trip_programs')) {
            Schema::create('trip_programs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->index();
                $table->string('title', 140);
                $table->text('summary')->nullable();
                $table->string('status', 12)->default('draft');   // draft | published
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trip_program_days')) {
            Schema::create('trip_program_days', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('program_id')->index();
                $table->unsignedSmallInteger('position')->default(1);
                $table->string('title', 140)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trip_program_items')) {
            Schema::create('trip_program_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('day_id')->index();
                $table->unsignedSmallInteger('position')->default(1);
                $table->string('kind', 8);                        // text | room | image
                $table->text('body')->nullable();
                $table->unsignedBigInteger('allocation_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_program_items');
        Schema::dropIfExists('trip_program_days');
        Schema::dropIfExists('trip_programs');
    }
};
