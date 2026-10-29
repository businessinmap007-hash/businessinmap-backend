<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Level;
use App\Models\Inventory\Move;
use App\Models\Inventory\Stocktake;
use App\Models\Inventory\StocktakeLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «الجرد» — counted against the ledger as it stood when the count STARTED: whatever was sold during the count is a move
 * of its own and stays one, so only the true difference becomes an adjustment. Blind by default — the counter does not
 * see the computed figure until he finishes (review), the standard way to keep a count honest.
 */
final class StocktakeService
{
    public function __construct(
        private readonly InventoryAccess $access,
        private readonly StockLedger $ledger,
    ) {}

    public function start(int $businessId, int $actorId, array $data): Stocktake
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = Stocktake::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing->load('lines');
        }

        $warehouse = $this->access->warehouse($businessId, $data['warehouse_id'] ?? null);

        return DB::transaction(function () use ($businessId, $actorId, $data, $uuid, $warehouse) {
            $count = Stocktake::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'warehouse_id' => $warehouse->id,
                'title' => $data['title'] ?? null,
                'blind' => (bool) ($data['blind'] ?? true),
                'user_id' => $actorId,
                'started_at' => now(),
            ]);

            $items = Item::query()->where('business_id', $businessId)->where('is_active', 1)
                ->when(! empty($data['item_ids']), fn ($q) => $q->whereIn('id', (array) $data['item_ids']))
                ->when(! empty($data['group_name']), fn ($q) => $q->where('group_name', $data['group_name']))
                ->pluck('id');

            $levels = Level::query()->where('warehouse_id', $warehouse->id)->whereIn('item_id', $items)->get()->groupBy('item_id');

            foreach ($items as $itemId) {
                $rows = $levels->get($itemId, collect());
                if ($rows->isEmpty()) {
                    StocktakeLine::create(['stocktake_id' => $count->id, 'item_id' => $itemId, 'lot_id' => 0, 'expected_qty' => 0]);
                }
                foreach ($rows as $level) {
                    StocktakeLine::create(['stocktake_id' => $count->id, 'item_id' => $itemId, 'lot_id' => (int) $level->lot_id, 'expected_qty' => (float) $level->qty]);
                }
            }

            return $count->fresh('lines');
        });
    }

    /** @param  list<array{item_id:int,lot_id?:int,counted_qty:float|null,reason?:string|null}>  $lines */
    public function count(Stocktake $count, array $lines): Stocktake
    {
        if (! in_array($count->status, [Stocktake::COUNTING, Stocktake::REVIEW], true)) {
            throw ValidationException::withMessages(['status' => __('انتهى هذا الجرد.')]);
        }

        foreach ($lines as $row) {
            $itemId = (int) $row['item_id'];
            if (! Item::query()->where('business_id', $count->business_id)->whereKey($itemId)->exists()) {
                throw ValidationException::withMessages(['item_id' => __('الصنف غير موجود.')]);
            }

            $line = StocktakeLine::query()->firstOrCreate(
                ['stocktake_id' => $count->id, 'item_id' => $itemId, 'lot_id' => (int) ($row['lot_id'] ?? 0)],
                ['expected_qty' => 0],
            );
            $line->update(['counted_qty' => $row['counted_qty'], 'reason' => $row['reason'] ?? $line->reason]);
        }

        return $count->fresh('lines');
    }

    public function finish(Stocktake $count): Stocktake
    {
        if ($count->status !== Stocktake::COUNTING) {
            throw ValidationException::withMessages(['status' => __('انتهى العدّ من قبل.')]);
        }

        $count->update(['status' => Stocktake::REVIEW]);

        return $count->fresh('lines');
    }

    /** Book every difference as one adjustment move per line; lines never counted change nothing. */
    public function apply(Stocktake $count, int $actorId): Stocktake
    {
        if (! in_array($count->status, [Stocktake::COUNTING, Stocktake::REVIEW], true)) {
            throw ValidationException::withMessages(['status' => __('انتهى هذا الجرد.')]);
        }

        return DB::transaction(function () use ($count, $actorId) {
            $specs = [];

            foreach ($count->lines()->whereNotNull('counted_qty')->get() as $line) {
                $diff = round((float) $line->counted_qty - (float) $line->expected_qty, 4);
                if ($diff == 0.0) {
                    continue;
                }
                $specs[] = [
                    'uuid' => $this->uuidFrom($count->uuid . '|' . $line->id),
                    'item_id' => $line->item_id,
                    'warehouse_id' => $count->warehouse_id,
                    'lot_id' => $line->lot_id ?: null,
                    'qty' => $diff,
                    'type' => Move::ADJUSTMENT,
                    'reason' => $line->reason,
                    'reference_type' => 'stocktake',
                    'reference_id' => $count->id,
                    'user_id' => $actorId,
                ];
            }

            if ($specs !== []) {
                $this->ledger->postMany((int) $count->business_id, $specs);
            }

            $count->update(['status' => Stocktake::APPLIED, 'applied_at' => now()]);

            return $count->fresh('lines');
        });
    }

    /** @return array<string,mixed> the count as its viewer may see it: no computed figures while a blind count runs */
    public function payload(Stocktake $count): array
    {
        $hide = $count->blind && $count->status === Stocktake::COUNTING;
        $items = Item::query()->whereIn('id', $count->lines->pluck('item_id'))->get()->keyBy('id');
        $value = 0.0;

        $lines = $count->lines->map(function (StocktakeLine $l) use ($hide, $items, &$value) {
            $item = $items->get($l->item_id);
            $diff = $l->counted_qty === null ? null : round((float) $l->counted_qty - (float) $l->expected_qty, 4);
            $diffValue = $diff === null ? null : round($diff * (float) optional($item)->avg_cost, 2);
            $value += (float) $diffValue;

            return [
                'id' => $l->id,
                'item_id' => $l->item_id,
                'item_name' => optional($item)->name_ar,
                'base_unit' => optional($item)->base_unit,
                'lot_id' => $l->lot_id ?: null,
                'expected_qty' => $hide ? null : (float) $l->expected_qty,
                'counted_qty' => $l->counted_qty === null ? null : (float) $l->counted_qty,
                'difference' => $hide ? null : $diff,
                'difference_value' => $hide ? null : $diffValue,
                'reason' => $l->reason,
            ];
        })->values()->all();

        return [
            'id' => $count->id,
            'uuid' => $count->uuid,
            'warehouse_id' => $count->warehouse_id,
            'title' => $count->title,
            'status' => $count->status,
            'blind' => (bool) $count->blind,
            'started_at' => optional($count->started_at)->toIso8601String(),
            'applied_at' => optional($count->applied_at)->toIso8601String(),
            'net_difference_value' => $hide ? null : round($value, 2),
            'lines' => $lines,
        ];
    }

    private function uuidFrom(string $seed): string
    {
        $h = md5($seed);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-b' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }
}
