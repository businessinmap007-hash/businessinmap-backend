<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Standalone menu orders (delivery / pickup) — not attached to a booking.
 * Total = food lines + delivery fee - discount. Deposits do not apply here
 * (that is a booking concept); see docs/services-blueprint.md.
 */
class MenuOrderService
{
    public function addLine(Order $order, int $menuId, int $qty, float $price, ?int $sizeId = null): void
    {
        DB::transaction(function () use ($order, $menuId, $qty, $price, $sizeId) {
            $qty = max(1, $qty);
            $price = round($price, 2);

            $order->items()->create([
                'menu_id' => $menuId,
                'offering_type' => \App\Models\MenuItem::class,
                'offering_id' => $menuId,
                'size_id' => $sizeId,
                'addons' => null,
                'qty' => $qty,
                'price' => $price,
                'total_price' => round($price * $qty, 2),
            ]);

            $this->recalc($order);
        });
    }

    public function removeLine(Order $order, int $orderItemId): void
    {
        DB::transaction(function () use ($order, $orderItemId) {
            $order->items()->whereKey($orderItemId)->delete();
            $this->recalc($order);
        });
    }

    public function recalc(Order $order): void
    {
        $foodTotal = round((float) $order->items()->sum('total_price'), 2);
        $deliveryFee = round((float) ($order->delivery_fee ?? 0), 2);
        $discount = round((float) ($order->discount ?? 0), 2);

        $order->update([
            'total' => $foodTotal,
            'final_total' => max(round($foodTotal + $deliveryFee - $discount, 2), 0),
        ]);
    }
}
