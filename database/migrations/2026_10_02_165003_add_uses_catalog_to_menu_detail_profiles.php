<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two shapes of «منيو تفصيلي»:
 *
 *  - backed by the catalog (mobiles, laptops, cars): the merchant PICKS a real
 *    model and states only what is his own for the unit — «التسعير والتفاصيل»;
 *  - not backed (a bedroom, a dining set, wood-alternative panels): there is no
 *    model to pick — the merchant names the item himself and states EVERY field
 *    of the kind for it, beside the choices he makes from option groups (wood,
 *    style), its photos, description and price.
 *
 * «اضافة منتج بتفتح السعر والتفاصيل والاختيار من الموبايلات وليس من نوم سفرة
 * انترية» — المالك، 2026-10-02: a furniture kind was being sent to a picker of
 * phones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_detail_profiles', function (Blueprint $table) {
            $table->boolean('uses_catalog')->default(true)->after('icon');
        });

        // «آثاث» (fur), made by the owner in «أشكال المنيو» for furniture: no catalog.
        DB::table('menu_detail_profiles')->where('code', 'fur')->update(['uses_catalog' => false]);
    }

    public function down(): void
    {
        Schema::table('menu_detail_profiles', function (Blueprint $table) {
            $table->dropColumn('uses_catalog');
        });
    }
};
