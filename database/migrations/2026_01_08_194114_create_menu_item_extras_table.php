<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_item_extras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            $table->string('group_key', 50)->nullable()->index(); // Sauces | Toppings ...
            $table->string('name_ar', 191);
            $table->string('name_en', 191)->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedSmallInteger('max_qty')->nullable(); // اختياري
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['menu_item_id', 'is_active'], 'extras_item_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_extras');
    }
};
