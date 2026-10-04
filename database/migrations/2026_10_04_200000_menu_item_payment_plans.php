<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «خطط الدفع كاش أو أقساط هى فى المنتجات الكبيرة وليس فى الأكل والشرب» — المالك، 2026-10-04.
 *
 * Paying by instalments is not a second PRODUCT of the item (the line carries ONE variant): it is a way
 * of paying for whatever the customer picked. So it leaves the variants and becomes the item's own
 * payment plans — months, a down payment and a markup over the cash price — chosen on the line next to
 * the size or the condition. Cash is the item's price and needs no row.
 *
 * Existing «تقسيط» variants are converted (markup = instalment price over the item's cash price) and
 * retired; placed orders keep what they froze.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_item_payment_plans')) {
            Schema::create('menu_item_payment_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('menu_item_id')->index();
                $table->unsignedSmallInteger('installment_months');
                // Per unit, paid with the first month.
                $table->decimal('installment_down', 10, 2)->nullable();
                // The instalment price over the cash price: 20 = 20% more than paying cash.
                $table->decimal('markup_percent', 14, 8)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('order_items', 'payment_plan_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_plan_id')->nullable();
            });
        }

        // ── convert what exists ────────────────────────────────────────────
        $variants = DB::table('menu_item_variants')->where('type', 'payment')->get();
        foreach ($variants->where('installment_months', '>', 1)->where('is_active', 1) as $variant) {
            $base = (float) DB::table('menu_items')->where('id', $variant->menu_item_id)->value('base_price');
            $price = (float) ($variant->price ?? 0) > 0 ? (float) $variant->price : $base + (float) ($variant->price_delta ?? 0);
            $markup = $base > 0 ? round(($price / $base - 1) * 100, 8) : 0;

            DB::table('menu_item_payment_plans')->insert([
                'menu_item_id' => $variant->menu_item_id, 'installment_months' => $variant->installment_months,
                'installment_down' => $variant->installment_down, 'markup_percent' => max(0, $markup), 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Cash and instalment alike leave the variants: cash is the price, an instalment is a plan.
        DB::table('menu_item_variants')->where('type', 'payment')->update(['is_active' => 0, 'is_default' => 0, 'updated_at' => now()]);

        // «الدفع والسداد» is no longer a price axis anywhere.
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $group = (int) DB::table('option_groups')->where('name_ar', 'الدفع والسداد')->value('id');
        if ($menu && $group) {
            DB::table('service_option_group_placements')
                ->where('platform_service_id', $menu)->where('option_group_id', $group)->where('usage', 'price_variant')
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
