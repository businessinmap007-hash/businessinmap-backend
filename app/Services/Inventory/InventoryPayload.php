<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Level;
use App\Models\Inventory\Move;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Receipt;
use App\Models\Inventory\Supplier;
use App\Models\User;

/** The JSON shapes the desktop suite and the phone read — one place, so both apps see the same thing. */
final class InventoryPayload
{
    /**
     * «٢٠ بالتة و٣٠ كرتونة و١٠ علب» — a base quantity in the biggest units that fit.
     *
     * @param  iterable<array{name:string,factor:float}>  $units
     */
    public static function breakdown(float $qty, string $baseUnit, iterable $units): string
    {
        $sign = $qty < 0 ? '-' : '';
        $left = abs($qty);
        $parts = [];
        $list = collect($units)->filter(fn ($u) => (float) $u['factor'] > 1)->sortByDesc('factor');

        foreach ($list as $unit) {
            $n = floor(round($left / (float) $unit['factor'], 6));
            if ($n >= 1) {
                $parts[] = self::num($n) . ' ' . $unit['name'];
                $left = round($left - $n * (float) $unit['factor'], 4);
            }
        }

        if ($left > 0 || $parts === []) {
            $parts[] = self::num($left) . ' ' . $baseUnit;
        }

        return $sign . implode(' و', $parts);
    }

    public static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** @return array<string,mixed> */
    public static function item(Item $item, bool $withLevels = true): array
    {
        $units = $item->relationLoaded('units') ? $item->units : $item->units()->get();
        $levels = $withLevels ? Level::query()->where('item_id', $item->id)->where('qty', '!=', 0)->get() : collect();
        $onHand = $withLevels ? round((float) $levels->sum('qty'), 4) : null;
        $unitRows = $units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'factor' => (float) $u->factor, 'barcode' => $u->barcode])->values()->all();

        return [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'name_ar' => $item->name_ar,
            'name_en' => $item->name_en,
            'group_name' => $item->group_name,
            'base_unit' => $item->base_unit,
            'unit_kind' => $item->unit_kind,
            'barcode' => $item->barcode,
            'reorder_level' => $item->reorder_level,
            'avg_cost' => (float) $item->avg_cost,
            'track_lots' => (bool) $item->track_lots,
            'is_active' => (bool) $item->is_active,
            'units' => $unitRows,
            'on_hand' => $onHand,
            'on_hand_display' => $onHand === null ? null : self::breakdown($onHand, $item->base_unit, $unitRows),
            'value' => $onHand === null ? null : round($onHand * (float) $item->avg_cost, 2),
            'below_reorder' => $onHand !== null && $item->reorder_level !== null && $onHand <= (float) $item->reorder_level,
            'levels' => $levels->map(fn ($l) => ['warehouse_id' => $l->warehouse_id, 'lot_id' => $l->lot_id ?: null, 'qty' => (float) $l->qty])->values()->all(),
            'updated_at' => optional($item->updated_at)->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public static function move(Move $move): array
    {
        return [
            'id' => $move->id,
            'uuid' => $move->uuid,
            'item_id' => $move->item_id,
            'warehouse_id' => $move->warehouse_id,
            'lot_id' => $move->lot_id,
            'qty' => (float) $move->qty,
            'unit_cost' => (float) $move->unit_cost,
            'type' => $move->type,
            'reason' => $move->reason,
            'reference_type' => $move->reference_type,
            'reference_id' => $move->reference_id,
            'reverses_move_id' => $move->reverses_move_id,
            'device_uuid' => $move->device_uuid,
            'user_id' => $move->user_id,
            'occurred_at' => optional($move->occurred_at)->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public static function supplier(Supplier $supplier, ?float $balance = null): array
    {
        $linked = $supplier->linked_business_id ? User::query()->find($supplier->linked_business_id, ['id', 'name']) : null;

        return [
            'id' => $supplier->id,
            'uuid' => $supplier->uuid,
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'linked_business_id' => $supplier->linked_business_id,
            'linked_business_name' => optional($linked)->name,
            'on_bim' => $linked !== null,
            'notes' => $supplier->notes,
            'is_active' => (bool) $supplier->is_active,
            'balance' => $balance,
            'updated_at' => optional($supplier->updated_at)->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public static function purchaseOrder(PurchaseOrder $order, bool $forSupplier = false): array
    {
        $order->loadMissing('lines', 'supplier');
        $names = Item::query()->whereIn('id', $order->lines->pluck('item_id'))->pluck('name_ar', 'id');

        $out = [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'number' => $order->number,
            'status' => $order->status,
            'supplier' => $order->supplier ? self::supplier($order->supplier) : null,
            'warehouse_id' => $order->warehouse_id,
            'total' => (float) $order->total,
            'expected_on' => optional($order->expected_on)->toDateString(),
            'notes' => $order->notes,
            'created_by' => $order->created_by,
            'approved_by' => $order->approved_by,
            'approved_at' => optional($order->approved_at)->toIso8601String(),
            'rejected_reason' => $order->rejected_reason,
            'sent_at' => optional($order->sent_at)->toIso8601String(),
            'supplier_confirmed_at' => optional($order->supplier_confirmed_at)->toIso8601String(),
            'lines' => $order->lines->map(fn ($l) => [
                'id' => $l->id,
                'item_id' => $l->item_id,
                'item_name' => $names[$l->item_id] ?? null,
                'unit_name' => $l->unit_name,
                'unit_factor' => (float) $l->unit_factor,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'total' => round((float) $l->qty * (float) $l->unit_price, 2),
                'received_qty' => (float) $l->received_qty,
                'billed_qty' => (float) $l->billed_qty,
            ])->values()->all(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'updated_at' => optional($order->updated_at)->toIso8601String(),
        ];

        if ($forSupplier) {
            $buyer = User::query()->find($order->business_id, ['id', 'name']);
            unset($out['supplier'], $out['warehouse_id'], $out['created_by'], $out['approved_by']);
            $out['buyer'] = ['id' => optional($buyer)->id, 'name' => optional($buyer)->name];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public static function receipt(Receipt $receipt): array
    {
        $receipt->loadMissing('lines');

        return [
            'id' => $receipt->id,
            'uuid' => $receipt->uuid,
            'number' => $receipt->number,
            'purchase_order_id' => $receipt->purchase_order_id,
            'supplier_id' => $receipt->supplier_id,
            'warehouse_id' => $receipt->warehouse_id,
            'user_id' => $receipt->user_id,
            'received_at' => optional($receipt->received_at)->toIso8601String(),
            'notes' => $receipt->notes,
            'lines' => $receipt->lines->map(fn ($l) => [
                'id' => $l->id, 'po_line_id' => $l->po_line_id, 'item_id' => $l->item_id, 'unit_name' => $l->unit_name,
                'unit_factor' => (float) $l->unit_factor, 'qty' => (float) $l->qty, 'damaged_qty' => (float) $l->damaged_qty,
                'unit_price' => (float) $l->unit_price, 'lot_id' => $l->lot_id,
            ])->values()->all(),
        ];
    }
}
