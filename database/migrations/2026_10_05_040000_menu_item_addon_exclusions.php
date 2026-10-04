<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اجعل الإضافات تشيك بوكس: اختار منها ما هو متاح لهذا النوع» — المالك، 2026-10-05. The shop prices its
 * services once; each ITEM then ticks the ones it offers. What is not ticked is stored here (an absence
 * of a row = offered, so every item that exists keeps what it had).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_addon_exclusions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_item_id');
            $table->unsignedBigInteger('option_id');
            $table->timestamps();
            $table->unique(['menu_item_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_addon_exclusions');
    }
};
