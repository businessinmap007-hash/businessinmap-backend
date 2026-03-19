<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_platform_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('platform_service_id')
                ->constrained('platform_services')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['category_id', 'platform_service_id'],
                'cps_category_service_unique'
            );

            $table->index(['category_id', 'is_active'], 'cps_category_active_idx');
            $table->index(['platform_service_id', 'is_active'], 'cps_service_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_platform_services');
    }
};