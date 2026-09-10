<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Undoes 2026_09_10_000001_create_business_retail_settings: a business-wide
 * currency minimum was the wrong shape for a wholesale retail minimum — see
 * 2026_09_10_000002, which adds a per-LISTING quantity minimum instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('business_retail_settings');
    }

    public function down(): void
    {
        Schema::create('business_retail_settings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
