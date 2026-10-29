<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Level;
use App\Models\Inventory\Move;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The ONE writer of the stock ledger (Odoo's stock moves, the restaurants' perpetual inventory).
 *
 * - A move is a signed quantity in the item's base unit. The balance is the sum of moves, kept in `inv_levels` in the
 *   same transaction — never a number written over a number, so two devices booking the same item at the same moment
 *   cannot erase each other.
 * - A move carries a client `uuid`: posting it again (a device replaying its queue after the connection came back)
 *   returns the move already booked and changes nothing.
 * - Cost is the weighted average (AVCO): every incoming move with a price recomputes the item's average —
 *   (on hand × average + qty × price) ÷ (on hand + qty); everything going out is valued at the average of that moment.
 * - Nothing is edited or deleted; {@see self::reverse()} answers a mistake with the opposite move.
 */
final class StockLedger
{
    /** Incoming types whose price moves the average cost. */
    private const COSTED_IN = [Move::RECEIPT, Move::OPENING];

    public function __construct(
        private readonly InventoryAccess $access,
        private readonly AvailabilitySync $availability,
    ) {}

    /**
     * @param  array{uuid?:string,item_id:int,warehouse_id?:int|null,lot_id?:int|null,qty:float|int|string,unit_cost?:float|null,
     *               type:string,reason?:string|null,reference_type?:string|null,reference_id?:int|null,reverses_move_id?:int|null,
     *               device_uuid?:string|null,user_id?:int|null,occurred_at?:mixed}  $spec
     */
    public function post(int $businessId, array $spec): Move
    {
        return $this->postMany($businessId, [$spec])[0];
    }

    /**
     * @param  list<array<string,mixed>>  $specs
     * @return list<Move>
     */
    public function postMany(int $businessId, array $specs): array
    {
        $touched = [];

        $moves = DB::transaction(function () use ($businessId, $specs, &$touched) {
            $out = [];

            foreach ($specs as $spec) {
                $uuid = (string) ($spec['uuid'] ?? '') ?: (string) Str::uuid();

                if ($existing = Move::query()->where('uuid', $uuid)->first()) {
                    if ((int) $existing->business_id !== $businessId) {
                        throw ValidationException::withMessages(['uuid' => __('معرّف الحركة مستخدم من قبل.')]);
                    }
                    $out[] = $existing;

                    continue;
                }

                $type = (string) $spec['type'];
                if (! in_array($type, Move::TYPES, true)) {
                    throw ValidationException::withMessages(['type' => __('نوع حركة غير معروف.')]);
                }

                $qty = round((float) $spec['qty'], 4);
                if ($qty == 0.0) {
                    continue;
                }

                /** @var Item|null $item */
                $item = Item::query()->where('business_id', $businessId)->lockForUpdate()->find((int) $spec['item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(['item_id' => __('الصنف غير موجود.')]);
                }

                $warehouse = $this->access->warehouse($businessId, isset($spec['warehouse_id']) ? (int) $spec['warehouse_id'] : null);
                $lotId = isset($spec['lot_id']) && $spec['lot_id'] ? (int) $spec['lot_id'] : null;
                $cost = isset($spec['unit_cost']) && $spec['unit_cost'] !== null ? round((float) $spec['unit_cost'], 4) : null;

                if ($qty > 0 && $cost !== null && in_array($type, self::COSTED_IN, true)) {
                    $onHand = max(0.0, (float) Level::query()->where('item_id', $item->id)->sum('qty'));
                    $item->avg_cost = $onHand > 0
                        ? round(($onHand * (float) $item->avg_cost + $qty * $cost) / ($onHand + $qty), 4)
                        : $cost;
                    $item->save();
                }

                $move = Move::create([
                    'uuid' => $uuid,
                    'business_id' => $businessId,
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'lot_id' => $lotId,
                    'qty' => $qty,
                    'unit_cost' => $cost ?? (float) $item->avg_cost,
                    'type' => $type,
                    'reason' => isset($spec['reason']) ? Str::limit((string) $spec['reason'], 188, '') : null,
                    'reference_type' => $spec['reference_type'] ?? null,
                    'reference_id' => $spec['reference_id'] ?? null,
                    'reverses_move_id' => $spec['reverses_move_id'] ?? null,
                    'device_uuid' => $spec['device_uuid'] ?? null,
                    'user_id' => $spec['user_id'] ?? null,
                    'occurred_at' => isset($spec['occurred_at']) && $spec['occurred_at'] ? Carbon::parse($spec['occurred_at']) : now(),
                ]);

                $touched[$item->id] ??= $this->onHand($item->id);
                $this->bump($businessId, $item->id, $warehouse->id, $lotId ?? 0, $qty);
                $out[] = $move;
            }

            return $out;
        });

        if ($touched !== []) {
            $this->availability->refresh($businessId, array_keys($touched));
            $this->alertCrossings($businessId, $touched);
        }

        return $moves;
    }

    /**
     * «قبل النفاد» — the owner hears once, at the moment an item drops to its reorder level (not on every sale below it).
     *
     * @param  array<int,float>  $before  item id => on hand before this batch
     */
    private function alertCrossings(int $businessId, array $before): void
    {
        foreach (Item::query()->whereIn('id', array_keys($before))->whereNotNull('reorder_level')->get() as $item) {
            $after = $this->onHand($item->id);
            $level = (float) $item->reorder_level;

            if ($before[$item->id] > $level && $after <= $level) {
                try {
                    app(\App\Services\Notifications\NotificationDispatcherService::class)->dispatch('inventory_low_stock', $businessId, [
                        'body_ar' => "وصل «{$item->name_ar}» إلى حد إعادة الطلب: المتبقي " . rtrim(rtrim(number_format($after, 2, '.', ''), '0'), '.') . " {$item->base_unit}.",
                        'body_en' => '"' . ($item->name_en ?: $item->name_ar) . "\" reached its reorder level: {$after} {$item->base_unit} left.",
                        'source_type' => 'inventory_item',
                        'source_id' => $item->id,
                        'meta' => ['item_id' => $item->id, 'on_hand' => $after],
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /** The opposite of a booked move — the only way a mistake is undone. Posting it twice reverses once. */
    public function reverse(Move $move, string $type = Move::REVERSAL, ?int $userId = null, ?string $reason = null, ?string $uuid = null): ?Move
    {
        if (Move::query()->where('reverses_move_id', $move->id)->exists()) {
            return null;
        }

        return $this->post((int) $move->business_id, [
            'uuid' => $uuid,
            'item_id' => $move->item_id,
            'warehouse_id' => $move->warehouse_id,
            'lot_id' => $move->lot_id,
            'qty' => -1 * (float) $move->qty,
            'unit_cost' => (float) $move->unit_cost,
            'type' => $type,
            'reason' => $reason,
            'reference_type' => $move->reference_type,
            'reference_id' => $move->reference_id,
            'reverses_move_id' => $move->id,
            'user_id' => $userId,
        ]);
    }

    public function onHand(int $itemId, ?int $warehouseId = null): float
    {
        return round((float) Level::query()->where('item_id', $itemId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))->sum('qty'), 4);
    }

    private function bump(int $businessId, int $itemId, int $warehouseId, int $lotId, float $qty): void
    {
        DB::table('inv_levels')->insertOrIgnore([
            'business_id' => $businessId, 'item_id' => $itemId, 'warehouse_id' => $warehouseId, 'lot_id' => $lotId,
            'qty' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('inv_levels')
            ->where('item_id', $itemId)->where('warehouse_id', $warehouseId)->where('lot_id', $lotId)
            ->update(['qty' => DB::raw('qty + ' . number_format($qty, 4, '.', '')), 'updated_at' => now()]);
    }
}
