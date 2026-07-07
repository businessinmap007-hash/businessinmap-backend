<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Attaches food (menu) orders to a dine-in booking and keeps the booking's
 * unified invoice (table charge + food) in sync. See docs/services-blueprint.md.
 */
class BookingFoodService
{
    public function __construct(protected ServiceExecutionEngine $engine)
    {
    }

    /**
     * The single dine-in food order for this booking (created on first use).
     */
    public function orderForBooking(Booking $booking): Order
    {
        return Order::query()->firstOrCreate(
            [
                'booking_id' => (int) $booking->id,
                'fulfillment_type' => Order::FULFILLMENT_DINE_IN,
            ],
            [
                'user_id' => (int) $booking->user_id,
                'business_id' => (int) $booking->business_id,
                'total' => 0,
                'delivery_fee' => 0,
                'discount' => 0,
                'final_total' => 0,
                'status' => 'pending',
                // The orders table was designed for delivery: address /
                // payment_method are NOT NULL. Dine-in has neither.
                'address' => '',
                'payment_method' => '',
            ]
        );
    }

    /**
     * Replace the booking's food lines and refresh its invoice.
     *
     * @param array<int, array{menu_id?:int, size_id?:int, addons?:mixed, qty?:int, price?:float}> $lines
     */
    public function syncFoodLines(Booking $booking, array $lines): Order
    {
        return DB::transaction(function () use ($booking, $lines) {
            $order = $this->orderForBooking($booking);
            $order->items()->delete();

            foreach ($lines as $line) {
                $qty = max(1, (int) ($line['qty'] ?? 1));
                $price = round((float) ($line['price'] ?? 0), 2);

                $lineMenuId = ! empty($line['menu_id']) ? (int) $line['menu_id'] : null;

                $order->items()->create([
                    'menu_id' => $lineMenuId,
                    'offering_type' => $lineMenuId ? \App\Models\MenuItem::class : null,
                    'offering_id' => $lineMenuId,
                    'size_id' => ! empty($line['size_id']) ? (int) $line['size_id'] : null,
                    'addons' => $line['addons'] ?? null,
                    'qty' => $qty,
                    'price' => $price,
                    'total_price' => round($price * $qty, 2),
                ]);
            }

            $this->recalcOrder($order);
            $this->refreshBookingInvoice($booking->refresh());

            return $order->refresh();
        });
    }

    /**
     * Append a single food line to the booking's dine-in order and refresh
     * the invoice. Price is the caller's responsibility to source from the
     * menu item (never trust a posted price).
     */
    public function addLine(Booking $booking, int $menuId, int $qty, float $price, ?int $sizeId = null): Order
    {
        return DB::transaction(function () use ($booking, $menuId, $qty, $price, $sizeId) {
            $order = $this->orderForBooking($booking);
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

            $this->recalcOrder($order);
            $this->refreshBookingInvoice($booking->refresh());

            return $order->refresh();
        });
    }

    /**
     * Remove one food line (by order_item id) from the booking's order.
     */
    public function removeLine(Booking $booking, int $orderItemId): void
    {
        DB::transaction(function () use ($booking, $orderItemId) {
            $order = $this->orderForBooking($booking);
            $order->items()->whereKey($orderItemId)->delete();

            $this->recalcOrder($order);
            $this->refreshBookingInvoice($booking->refresh());
        });
    }

    protected function recalcOrder(Order $order): void
    {
        $total = round((float) $order->items()->sum('total_price'), 2);
        $finalTotal = round($total + (float) ($order->delivery_fee ?? 0) - (float) ($order->discount ?? 0), 2);

        $order->update([
            'total' => $total,
            'final_total' => max($finalTotal, 0),
        ]);
    }

    /**
     * Compute the booking's unified invoice: table charge (per the resolved
     * BusinessServicePrice's charge mode) combined with the attached food, plus
     * the deposit on the combined total per the business's policy.
     */
    public function unifiedInvoice(Booking $booking): array
    {
        $foodTotal = round($booking->foodTotal(), 2);
        $quantity = max(1, (int) ($booking->quantity ?? 1));

        $bsp = $this->engine->resolveBusinessPriceForBooking($booking);

        if (! $bsp) {
            return [
                'currency' => 'EGP',
                'charge_mode' => 'unknown',
                'table_charge' => 0.00,
                'food_total' => $foodTotal,
                'total' => $foodTotal,
                'deposit_amount' => 0.00,
            ];
        }

        $total = $bsp->unifiedTotal($foodTotal, $quantity);
        $tableCharge = round(max($total - $foodTotal, 0), 2);

        $depositAmount = 0.00;
        $supportsDeposit = (bool) ($bsp->service?->supports_deposit ?? false);

        if ($supportsDeposit && (bool) ($bsp->deposit_enabled ?? false)) {
            $percent = max(0, min((int) ($bsp->deposit_percent ?? 0), 100));
            $depositAmount = round($total * $percent / 100, 2);
        }

        return [
            'currency' => (string) ($bsp->currency ?: 'EGP'),
            'charge_mode' => $bsp->chargeMode(),
            'table_charge' => $tableCharge,
            'food_total' => $foodTotal,
            'total' => round($total, 2),
            'deposit_amount' => $depositAmount,
        ];
    }

    /**
     * Persist the unified total onto the booking and snapshot it in meta.
     * (The deposit *record* lifecycle is handled separately by the deposit
     * services; this keeps booking.price and the invoice snapshot current.)
     */
    public function refreshBookingInvoice(Booking $booking): void
    {
        $invoice = $this->unifiedInvoice($booking);

        $meta = $booking->metaArray();
        $meta['unified_invoice'] = $invoice;

        $booking->meta = $meta;
        $booking->price = $invoice['total'];
        $booking->save();
    }
}
