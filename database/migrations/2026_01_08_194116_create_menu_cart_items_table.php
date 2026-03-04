<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_cart_id')->constrained('menu_carts')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            $table->unsignedBigInteger('variant_id')->nullable()->index(); // menu_item_variants.id (بدون FK لتفادي مشاكل لو حذف variant)
            $table->unsignedInteger('qty')->default(1);

            $table->decimal('unit_price', 10, 2)->default(0);  // سعر الوحدة وقت الإضافة
            $table->decimal('total_price', 10, 2)->default(0); // unit_price*qty + extras

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['menu_cart_id', 'menu_item_id'], 'cart_items_cart_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_cart_items');
    }
};
