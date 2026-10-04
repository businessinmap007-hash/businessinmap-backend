<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use App\Models\MenuItemPaymentPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment plans of an item — «كاش أو أقساط» — for the big-ticket goods only (the detail types that say
 * `allows_payment_plans`: phones and computers, cars, furniture), never for food. The merchant writes
 * what the instalment plan COSTS in total; the plan keeps it as a markup over the cash price, so a
 * later change of the cash price moves the plans with it.
 */
final class PaymentPlans
{
    /** Does what this item IS allow instalments? */
    public function allowedFor(MenuItem $item): bool
    {
        $lineGroup = $item->lineOption()?->group;
        $type = $lineGroup?->detail_type;

        return $type !== null && (bool) DB::table('menu_detail_types')->where('code', $type)->value('allows_payment_plans');
    }

    /**
     * @return list<array{id:int,months:int,down:?float,markup_percent:float,unit_price:float,monthly:float}>
     */
    public function present(MenuItem $item, ?float $cash = null): array
    {
        if (! $this->allowedFor($item)) {
            return [];
        }
        $cash ??= (float) $item->base_price;

        return MenuItemPaymentPlan::query()->where('menu_item_id', $item->id)->active()->orderBy('installment_months')->get()
            ->map(function (MenuItemPaymentPlan $plan) use ($cash) {
                $unit = $plan->unitPrice($cash);
                $down = (float) $plan->installment_down;

                return [
                    'id' => (int) $plan->id,
                    'months' => (int) $plan->installment_months,
                    'down' => $down > 0 ? $down : null,
                    'markup_percent' => (float) $plan->markup_percent,
                    'unit_price' => $unit,
                    // The regular month, per unit: what is left after the down payment, over the months.
                    'monthly' => round(($unit - $down) / max(1, (int) $plan->installment_months), 2),
                ];
            })->all();
    }

    /**
     * Replace the item's plans. Each `{months, down?, total_price}`: what ONE unit costs when paid that way.
     *
     * @param  list<array<string,mixed>>  $plans
     * @return list<array<string,mixed>>
     */
    public function replace(MenuItem $item, array $plans): array
    {
        if ($plans !== [] && ! $this->allowedFor($item)) {
            throw ValidationException::withMessages(['plans' => __('هذا النوع من المنتجات لا يُباع بالتقسيط.')]);
        }

        $cash = (float) $item->base_price;
        $rows = [];
        foreach ($plans as $plan) {
            $months = (int) ($plan['months'] ?? 0);
            $total = round((float) ($plan['total_price'] ?? 0), 2);
            $down = round((float) ($plan['down'] ?? 0), 2);

            if ($cash <= 0 || $total < $cash) {
                throw ValidationException::withMessages(['plans' => __('سعر التقسيط لا يقل عن السعر كاش.')]);
            }
            if ($down < 0 || $down >= $total) {
                throw ValidationException::withMessages(['plans' => __('المقدم يجب أن يكون أقل من سعر التقسيط.')]);
            }
            $rows[] = ['installment_months' => $months, 'installment_down' => $down > 0 ? $down : null, 'markup_percent' => round(($total / $cash - 1) * 100, 8)];
        }

        DB::transaction(function () use ($item, $rows) {
            MenuItemPaymentPlan::query()->where('menu_item_id', $item->id)->delete();
            foreach ($rows as $row) {
                MenuItemPaymentPlan::query()->create($row + ['menu_item_id' => $item->id, 'is_active' => true]);
            }
        });

        return $this->present($item->refresh());
    }

    /** The plan a customer picked, only if it is this item's, active, and the item allows plans. */
    public function resolve(MenuItem $item, int $planId): MenuItemPaymentPlan
    {
        $plan = MenuItemPaymentPlan::query()->active()->where('menu_item_id', $item->id)->find($planId);

        if (! $plan || ! $this->allowedFor($item)) {
            throw ValidationException::withMessages(['plan_id' => __('خطة الدفع المختارة غير متاحة.')]);
        }

        return $plan;
    }
}
