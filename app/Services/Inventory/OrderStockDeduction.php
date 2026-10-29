<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Move;
use App\Models\Order;
use App\Models\User;

/**
 * A sale made through the apps takes its stock when the merchant ACCEPTS the order — by each line's recipe, its size and
 * its extras — and gives it back by opposite moves if the order is cancelled after that. Booked once per order whatever
 * calls it; never blocks the order: stock may go below zero, and the dashboard shows it.
 *
 * Runs in read-only (unpaid subscription) too, so the balance stays true.
 */
final class OrderStockDeduction
{
    public const REFERENCE = 'order';

    public function __construct(
        private readonly InventoryAccess $access,
        private readonly RecipeService $recipes,
        private readonly StockLedger $ledger,
    ) {}

    public function deduct(Order $order): void
    {
        $business = User::query()->find($order->business_id);

        if (! $business || ! $this->access->enabled($business) || ! $this->access->has($business, InventoryAccess::SALES_LINK)) {
            return;
        }

        if (Move::query()->where('reference_type', self::REFERENCE)->where('reference_id', $order->id)->exists()) {
            return;
        }

        $need = [];

        foreach ($order->items()->get() as $line) {
            $owner = RecipeService::ownerFor((string) $line->offering_type);
            if ($owner === null) {
                continue;
            }

            $extras = $owner === RecipeService::MENU_ITEM ? array_values(array_filter((array) $line->addons, 'is_array')) : [];

            foreach ($this->recipes->consumption($owner, (int) $line->offering_id, (float) $line->qty, $line->size_id ? (int) $line->size_id : null, $extras) as $itemId => $qty) {
                $need[$itemId] = ($need[$itemId] ?? 0) + $qty;
            }
        }

        if ($need === []) {
            return;
        }

        $warehouse = $this->access->defaultWarehouse((int) $business->id);
        $specs = [];

        foreach ($need as $itemId => $qty) {
            $specs[] = [
                'item_id' => $itemId,
                'warehouse_id' => $warehouse->id,
                'qty' => -1 * $qty,
                'type' => Move::SALE,
                'reference_type' => self::REFERENCE,
                'reference_id' => (int) $order->id,
            ];
        }

        $this->ledger->postMany((int) $business->id, $specs);
    }

    public function restore(Order $order): void
    {
        $moves = Move::query()
            ->where('reference_type', self::REFERENCE)->where('reference_id', $order->id)->where('type', Move::SALE)
            ->get();

        foreach ($moves as $move) {
            $this->ledger->reverse($move, Move::SALE_REVERSAL, null, __('إلغاء الطلب #:id', ['id' => $order->id]));
        }
    }

    /** Called from Order's model hooks: a stock problem is reported, never thrown into the order's own save. */
    public function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
