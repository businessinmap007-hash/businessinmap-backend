<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu sections — lets a restaurant group its menu_items into named sections
 * (مقبلات / أطباق رئيسية / حلويات / مشروبات). Each section belongs to one
 * business; each menu item optionally points at one section. The legacy unused
 * menu_items.category_id column is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_sections')) {
            Schema::create('menu_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string('name_ar', 191);
                $table->string('name_en', 191)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['business_id', 'is_active', 'sort_order'], 'menu_sections_business_active_sort_idx');
            });
        }

        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'menu_section_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->unsignedBigInteger('menu_section_id')->nullable()->after('business_id');
                $table->foreign('menu_section_id', 'menu_items_section_fk')
                    ->references('id')->on('menu_sections')->nullOnDelete()->cascadeOnUpdate();
                $table->index(['menu_section_id', 'sort_order'], 'menu_items_section_sort_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items') && Schema::hasColumn('menu_items', 'menu_section_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropForeign('menu_items_section_fk');
                $table->dropIndex('menu_items_section_sort_idx');
                $table->dropColumn('menu_section_id');
            });
        }

        Schema::dropIfExists('menu_sections');
    }
};
