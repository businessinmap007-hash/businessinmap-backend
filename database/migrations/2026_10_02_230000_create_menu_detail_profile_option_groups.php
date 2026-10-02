<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The DESCRIPTIVE fields of a menu kind: not catalog attributes but option
 * groups the merchant describes the item from — «طراز الأثاث»، «نظام التصنيع»،
 * «أنواع الأخشاب» on a bedroom. The admin ticks, per kind in «أشكال المنيو», which
 * of them the kind's item form offers and in what order; a kind with none ticked
 * keeps offering every descriptive group «مكونات الخدمة» made for the trade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_detail_profile_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_detail_profile_id')->constrained('menu_detail_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('option_group_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_detail_profile_id', 'option_group_id'], 'mdpog_profile_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_detail_profile_option_groups');
    }
};
