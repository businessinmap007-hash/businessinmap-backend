<?php

namespace App\Services\Inventory;

use App\Models\BusinessCatalogListing;
use App\Models\Inventory\Move;
use App\Models\Inventory\PosSale;
use App\Models\Inventory\PosSaleLine;
use App\Models\Inventory\PosShift;
use App\Models\Inventory\Setting;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «الكاشير» — a counter sale on the business's own computer. It sells what the apps sell (menu items, retail listings) at
 * the prices the business set — resolved here, never taken from the device — and takes the stock by the same recipes as
 * an app order. The money is cash in the drawer, outside the app: the wallet never pays for goods. A shift ends with the
 * drawer counted against what the sales say it should hold.
 */
final class PosService
{
    public function __construct(
        private readonly InventoryAccess $access,
        private readonly RecipeService $recipes,
        private readonly StockLedger $ledger,
    ) {}

    public function openShift(int $businessId, int $actorId, array $data): PosShift
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        return PosShift::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()
            ?? PosShift::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'device_uuid' => $data['device_uuid'] ?? null,
                'user_id' => $actorId,
                'opening_cash' => round((float) ($data['opening_cash'] ?? 0), 2),
                'opened_at' => isset($data['opened_at']) ? Carbon::parse($data['opened_at']) : now(),
            ]);
    }

    /** @return array{shift:PosShift,expected_cash:float,sales_count:int,sales_total:float,difference:float|null} */
    public function closeShift(PosShift $shift, float $countedCash): array
    {
        if (! $shift->closed_at) {
            $shift->update(['counted_cash' => round($countedCash, 2), 'closed_at' => now()]);
        }

        return $this->shiftSummary($shift->fresh());
    }

    /** @return array{shift:PosShift,expected_cash:float,sales_count:int,sales_total:float,difference:float|null} */
    public function shiftSummary(PosShift $shift): array
    {
        $sales = PosSale::query()->where('shift_id', $shift->id)->where('status', PosSale::PAID);
        $total = round((float) $sales->sum('total'), 2);
        $expected = round((float) $shift->opening_cash + $total, 2);

        return [
            'shift' => $shift,
            'expected_cash' => $expected,
            'sales_count' => (int) $sales->count(),
            'sales_total' => $total,
            'difference' => $shift->counted_cash === null ? null : round((float) $shift->counted_cash - $expected, 2),
        ];
    }

    public function sell(int $businessId, int $actorId, array $data): PosSale
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = PosSale::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing->load('lines');
        }

        $shift = null;
        if (! empty($data['shift_id'])) {
            $shift = PosShift::query()->where('business_id', $businessId)->find((int) $data['shift_id']);
            if (! $shift) {
                throw ValidationException::withMessages(['shift_id' => __('الوردية غير موجودة.')]);
            }
        }

        $priced = array_map(fn ($row) => $this->price($businessId, $row), $data['lines']);
        $total = round(array_sum(array_column($priced, 'total')), 2);
        $paid = round((float) ($data['paid'] ?? $total), 2);

        if ($paid + 0.001 < $total) {
            throw ValidationException::withMessages(['paid' => __('المدفوع أقل من الإجمالي (:total).', ['total' => $total])]);
        }

        $warehouse = $this->access->warehouse($businessId, $data['warehouse_id'] ?? null);

        return DB::transaction(function () use ($businessId, $actorId, $data, $uuid, $shift, $priced, $total, $paid, $warehouse) {
            $sale = PosSale::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'number' => $this->nextNumber($businessId),
                'shift_id' => $shift?->id,
                'device_uuid' => $data['device_uuid'] ?? null,
                'user_id' => $actorId,
                'warehouse_id' => $warehouse->id,
                'total' => $total,
                'paid' => $paid,
                'sold_at' => isset($data['sold_at']) ? Carbon::parse($data['sold_at']) : now(),
            ]);

            $need = [];
            foreach ($priced as $line) {
                PosSaleLine::create(['sale_id' => $sale->id] + $line);
                foreach ($this->recipes->consumption($line['owner_type'], $line['owner_id'], $line['qty'], $line['variant_id'], (array) $line['extras']) as $itemId => $qty) {
                    $need[$itemId] = ($need[$itemId] ?? 0) + $qty;
                }
            }

            $specs = [];
            foreach ($need as $itemId => $qty) {
                $specs[] = [
                    'uuid' => $this->uuidFrom($uuid . '|' . $itemId),
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouse->id,
                    'qty' => -1 * $qty,
                    'type' => Move::SALE,
                    'reference_type' => 'pos_sale',
                    'reference_id' => $sale->id,
                    'device_uuid' => $data['device_uuid'] ?? null,
                    'user_id' => $actorId,
                    'occurred_at' => $sale->sold_at,
                ];
            }
            if ($specs !== []) {
                $this->ledger->postMany($businessId, $specs);
            }

            return $sale->fresh('lines');
        });
    }

    /** «مرتجع» — the whole sale back: its stock returns by opposite moves, and it leaves the shift's expected cash. */
    public function returnSale(PosSale $sale, int $actorId): PosSale
    {
        if ($sale->status === PosSale::RETURNED) {
            return $sale->load('lines');
        }

        DB::transaction(function () use ($sale, $actorId) {
            foreach (Move::query()->where('reference_type', 'pos_sale')->where('reference_id', $sale->id)->where('type', Move::SALE)->get() as $move) {
                $this->ledger->reverse($move, Move::SALE_REVERSAL, $actorId, __('مرتجع فاتورة كاشير #:n', ['n' => $sale->number]));
            }
            $sale->update(['status' => PosSale::RETURNED, 'returned_at' => now()]);
        });

        return $sale->fresh('lines');
    }

    /** @return array{owner_type:string,owner_id:int,name:string,variant_id:int|null,extras:list<array{id:int,qty:int}>,qty:float,unit_price:float,total:float} */
    private function price(int $businessId, array $row): array
    {
        $type = (string) $row['owner_type'];
        $id = (int) $row['owner_id'];
        $qty = (float) $row['qty'];

        if ($qty <= 0) {
            throw ValidationException::withMessages(['lines' => __('الكمية يجب أن تكون أكبر من صفر.')]);
        }

        if ($type === RecipeService::MENU_ITEM) {
            $item = MenuItem::query()->where('business_id', $businessId)->where('is_active', 1)->find($id);
            if (! $item) {
                throw ValidationException::withMessages(['lines' => __('صنف المنيو غير موجود.')]);
            }

            $unit = (float) $item->base_price;
            $name = (string) ($item->name_ar ?: $item->name_en);
            $variantId = null;

            if (! empty($row['variant_id'])) {
                $variant = MenuItemVariant::query()->where('menu_item_id', $item->id)->find((int) $row['variant_id']);
                if (! $variant) {
                    throw ValidationException::withMessages(['lines' => __('المقاس ليس من هذا الصنف.')]);
                }
                $unit = $variant->resolvePrice($unit);
                $variantId = $variant->id;
                $name .= ' — ' . $variant->display_name;
            }

            $extras = [];
            foreach ((array) ($row['extras'] ?? []) as $extra) {
                $model = MenuItemExtra::query()->where('menu_item_id', $item->id)->where('is_active', 1)->find((int) ($extra['id'] ?? 0));
                if (! $model) {
                    throw ValidationException::withMessages(['lines' => __('الإضافة ليست من هذا الصنف.')]);
                }
                $count = max(1, (int) ($extra['qty'] ?? 1));
                $unit += (float) $model->price * $count;
                $extras[] = ['id' => (int) $model->id, 'qty' => $count];
            }

            return ['owner_type' => $type, 'owner_id' => $id, 'name' => $name, 'variant_id' => $variantId, 'extras' => $extras,
                'qty' => $qty, 'unit_price' => round($unit, 2), 'total' => round($unit * $qty, 2)];
        }

        if ($type === RecipeService::RETAIL_LISTING) {
            $listing = BusinessCatalogListing::query()->where('business_id', $businessId)->where('is_active', 1)->find($id);
            if (! $listing) {
                throw ValidationException::withMessages(['lines' => __('المنتج غير موجود.')]);
            }
            $unit = (float) $listing->price;

            return ['owner_type' => $type, 'owner_id' => $id, 'name' => $listing->offeringLabel('#' . $id), 'variant_id' => null, 'extras' => [],
                'qty' => $qty, 'unit_price' => round($unit, 2), 'total' => round($unit * $qty, 2)];
        }

        throw ValidationException::withMessages(['lines' => __('نوع الصنف غير معروف.')]);
    }

    private function nextNumber(int $businessId): int
    {
        $this->access->settings($businessId);
        $row = Setting::query()->where('business_id', $businessId)->lockForUpdate()->first();
        $number = (int) $row->next_sale_number;
        $row->update(['next_sale_number' => $number + 1]);

        return $number;
    }

    private function uuidFrom(string $seed): string
    {
        $h = md5($seed);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-c' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }
}
