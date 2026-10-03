<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «خلي طريقة الدفع تقسم دفعات اذا كانت قسط على تواريخ حسب عدد الاشهر» — المالك،
 * 2026-10-03. The merchant says how many months an instalment price runs
 * (`menu_item_variants.installment_months`); the order line freezes it
 * (`order_items.installment_months`); a placed order carries its schedule — one row
 * per month with its due date and amount (`order_installments`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_variants', function (Blueprint $table) {
            $table->unsignedSmallInteger('installment_months')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('installment_months')->nullable();
        });

        Schema::create('order_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedSmallInteger('seq');
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_installments');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('installment_months'));
        Schema::table('menu_item_variants', fn (Blueprint $t) => $t->dropColumn('installment_months'));
    }
};
