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
    /**
     * Add any offering to the order — a bespoke menu item or a retail catalog
     * listing. Price is sourced by the caller from the offering (never posted).
     */
    public function addOffering(
        Order $order,
        string $offeringType,
        int $offeringId,
        int $qty,
        float $price,
        ?int $menuId = null,
        ?int $sizeId = null,
        ?array $addons = null
    ): void {
        DB::transaction(function () use ($order, $offeringType, $offeringId, $qty, $price, $menuId, $sizeId, $addons) {
            $qty = max(1, $qty);
            $price = round($price, 2);

            $order->items()->create([
                'menu_id' => $menuId,
                'offering_type' => $offeringType,
                'offering_id' => $offeringId,
                'size_id' => $sizeId,
                'addons' => $addons ?: null,
                'qty' => $qty,
                'price' => $price,
                'total_price' => round($price * $qty, 2),
            ]);

            $this->recalc($order);
        });
    }

    /** Convenience: add a bespoke menu item line. */
    public function addLine(Order $order, int $menuId, int $qty, float $price, ?int $sizeId = null): void
    {
        $this->addOffering($order, \App\Models\MenuItem::class, $menuId, $qty, $price, $menuId, $sizeId);
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
