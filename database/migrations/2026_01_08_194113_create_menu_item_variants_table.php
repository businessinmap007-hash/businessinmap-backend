<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();

            $table->string('type', 50)->index(); // size | color | ...
            $table->string('name_ar', 191);
            $table->string('name_en', 191)->nullable();

            // إمّا سعر ثابت أو فرق سعر
            $table->decimal('price', 10, 2)->nullable();        // سعر variant (لو عايز سعر مباشر)
            $table->decimal('price_delta', 10, 2)->nullable();  // فرق عن base_price

            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['menu_item_id', 'type', 'is_active'], 'variants_item_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variants');
    }
};
