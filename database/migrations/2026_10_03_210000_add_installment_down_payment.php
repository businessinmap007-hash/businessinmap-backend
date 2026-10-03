<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «دفعة مقدمة مثلا 12000 فيقسم الباقى على المدة» — المالك، 2026-10-03. An instalment
 * price may carry a down payment (per unit): it is paid with the first month, and
 * what is left is split over the months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_variants', function (Blueprint $table) {
            $table->decimal('installment_down', 12, 2)->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('installment_down', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('installment_down'));
        Schema::table('menu_item_variants', fn (Blueprint $t) => $t->dropColumn('installment_down'));
    }
};
