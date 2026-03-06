<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_service_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('platform_service_id');

            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);

            // Optional fee override per business/service
            $table->enum('fee_type', ['fixed','percent'])->nullable();
            $table->decimal('fee_value', 12, 2)->nullable();

            $table->timestamps();

            $table->unique(['business_id','platform_service_id']);

            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('platform_service_id')->references('id')->on('platform_services')->cascadeOnDelete();

            $table->index(['platform_service_id','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_service_prices');
    }
};