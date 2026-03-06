<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_fees', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('service_id');

            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->enum('fee_type', ['fixed', 'percent'])->nullable();
            $table->decimal('fee_value', 12, 2)->nullable();

            $table->json('rules')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'service_id']);

            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();

            $table->index(['business_id']);
            $table->index(['service_id']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fees');
    }
};