<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_service_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('platform_service_id')
                ->constrained('platform_services')
                ->cascadeOnDelete();

            $table->json('config')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['category_id', 'platform_service_id'], 'csc_category_service_unique');
            $table->index(['platform_service_id', 'is_active'], 'csc_service_active_index');
            $table->index(['category_id', 'is_active'], 'csc_category_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_service_configs');
    }
};