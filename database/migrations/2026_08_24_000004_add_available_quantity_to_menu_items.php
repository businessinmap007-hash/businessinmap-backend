<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «يخضع للتسعير والكمية المتاحة ووحدة البيع» — المالك، 2026-08-24.
 *
 * A greengrocer prices «برتقال» at 22 the kilo and has forty kilos. The price
 * and the unit were already there; the quantity had nowhere to live, so «نفد»
 * could only be said by switching the whole row off — which loses the price
 * with it and reads to the customer as «we do not sell oranges».
 *
 * NULL means «لا أتابع الكمية», which is what every existing row is and what a
 * restaurant means: a kitchen does not count sandwiches. Zero is a claim, and
 * a different one: «معروض، ونفد».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('available_quantity')->nullable()->after('sale_unit');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('available_quantity');
        });
    }
};
