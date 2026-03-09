<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('service_id'); // platform_services.id

            $table->string('item_type', 50)->nullable();   // room, suite, apartment, court ...
            $table->string('title', 191);                  // Room 101 / Apartment 3A / Court 1
            $table->string('code', 100)->nullable();       // 101 / A3 / C1

            $table->decimal('price', 12, 2)->default(0);

            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            $table->boolean('is_active')->default(true);

            // Optional override later if needed
            $table->boolean('deposit_enabled')->default(false);
            $table->unsignedTinyInteger('deposit_percent')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id']);
            $table->index(['service_id']);
            $table->index(['is_active']);
            $table->index(['item_type']);

            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('platform_services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_items');
    }
};