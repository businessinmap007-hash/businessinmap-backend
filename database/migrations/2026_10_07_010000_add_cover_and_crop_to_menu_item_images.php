<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «اختيار أى صورة تظهر على الكارت + إعدادات قص الصورة» — المالك، 2026-10-06.
 *   menu_items.cover_image_id — the photo the CARD shows (null = the first one);
 *   images.focal_x / focal_y / zoom — which part of a photo the card shows: the point to keep in the middle (0..1) and
 *   how far in to go (1 = the whole photo), so the empty part is not shown and the product is not cut off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cover_image_id')->nullable()->after('image');
        });

        Schema::table('images', function (Blueprint $table) {
            $table->decimal('focal_x', 4, 3)->default(0.5)->after('purpose');
            $table->decimal('focal_y', 4, 3)->default(0.5)->after('focal_x');
            $table->decimal('zoom', 3, 2)->default(1.00)->after('focal_y');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['focal_x', 'focal_y', 'zoom']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('cover_image_id');
        });
    }
};
