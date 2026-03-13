<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_services', function (Blueprint $table) {
            $table->id();

            // booking, menu, delivery ...
            $table->string('key')->unique();

            $table->string('name_ar');
            $table->string('name_en')->nullable();

            $table->boolean('is_active')->default(true);

            // Deposit rules (عامّة للخدمة)
            $table->boolean('supports_deposit')->default(false);
            $table->unsignedTinyInteger('max_deposit_percent')->default(0); // 0..100

            // Fee defaults (اختياري)
            $table->enum('fee_type', ['fixed','percent'])->nullable(); // fixed or percent
            $table->decimal('fee_value', 12, 2)->nullable();

            // Future rules
            $table->json('rules')->nullable();

            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_services');
    }
};