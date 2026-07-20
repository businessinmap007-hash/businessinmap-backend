<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an arbitration session costs, per platform service.
 *
 * Set by the platform, not by the arbitrator who will hear the case: an
 * arbitrator who prices their own session is one who profits from escalating
 * it, and a party cannot argue with a number invented for their case alone.
 *
 * ONE price per session — not a client price and a business price. The session
 * is a single piece of work whoever asked for it, and two prices would mean the
 * cost of justice depends on which side of the counter you stand on.
 *
 * Stored as an unsigned INTEGER because the price is a whole number by policy;
 * making it decimal would invite 33.33 back into a figure that is meant to be
 * quotable in a sentence.
 *
 * A NULL service is the fallback used by any service with no row of its own,
 * so a new platform service is never silently free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_fees', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('platform_service_id')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('platform_service_id', 'dispute_fees_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_fees');
    }
};
