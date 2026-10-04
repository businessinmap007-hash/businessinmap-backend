<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A markup of 233.3333% turned 100000 into 99999.99: the markup is kept to eight decimals so the price
 * the merchant wrote comes back to the cent. Plans converted from variants are recomputed exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE menu_item_payment_plans MODIFY markup_percent DECIMAL(14,8) NOT NULL DEFAULT 0');

        foreach (DB::table('menu_item_payment_plans')->get() as $plan) {
            $variant = DB::table('menu_item_variants')->where('menu_item_id', $plan->menu_item_id)->where('type', 'payment')->where('installment_months', $plan->installment_months)->first();
            $base = (float) DB::table('menu_items')->where('id', $plan->menu_item_id)->value('base_price');
            if ($variant && $base > 0) {
                $price = (float) ($variant->price ?? 0) > 0 ? (float) $variant->price : $base + (float) ($variant->price_delta ?? 0);
                DB::table('menu_item_payment_plans')->where('id', $plan->id)->update(['markup_percent' => max(0, round(($price / $base - 1) * 100, 8))]);
            }
        }
    }

    public function down(): void
    {
    }
};
