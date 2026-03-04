<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_id')->index();   // المطعم/المحل
            $table->unsignedBigInteger('category_id')->nullable()->index(); // قسم المنيو (اختياري)

            $table->string('name_ar', 191);
            $table->string('name_en', 191)->nullable();

            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            $table->string('image', 191)->nullable();

            $table->decimal('base_price', 10, 2)->default(0); // يستخدم لو مفيش variants
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();

            $table->timestamps();

            $table->index(['business_id', 'is_active', 'sort_order'], 'menu_items_business_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
