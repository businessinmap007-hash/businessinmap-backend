<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_cart_item_extras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_cart_item_id')->constrained('menu_cart_items')->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained('menu_item_extras')->cascadeOnDelete();

            $table->unsignedInteger('qty')->default(1);
            $table->decimal('price', 10, 2)->default(0); // Snapshot لسعر الإضافة وقت الإضافة

            $table->timestamps();

            $table->unique(['menu_cart_item_id', 'extra_id'], 'cart_item_extras_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_cart_item_extras');
    }
};
