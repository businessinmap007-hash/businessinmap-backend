<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Move;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderLine;
use App\Models\Inventory\Receipt;
use App\Models\Inventory\ReceiptLine;
use App\Models\Inventory\Setting;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\SupplierBill;
use App\Models\Inventory\SupplierBillLine;
use App\Models\Inventory\SupplierPayment;
use App\Models\Inventory\SupplierReturn;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Purchases, the way Odoo and the restaurant systems do them: a purchase order (draft → the owner's approval above the
 * limit HE sets → sent), receipts against it — whole or partial, each line a stock move in at its price — the supplier's
 * bill matched three ways (ordered / received / billed), payments RECORDED only (the money moves outside the app; the
 * wallet never pays for goods), and returns going back out at their cost.
 *
 * A supplier that is itself a business on BIM receives the order in its own app and confirms it.
 */
final class PurchaseService
{
    public function __construct(
        private readonly InventoryAccess $access,
        private readonly StockLedger $ledger,
    ) {}

    // ── suppliers ────────────────────────────────────────────────────────────────────────────────────────────────────

    public function saveSupplier(int $businessId, array $data, ?Supplier $supplier = null): Supplier
    {
        if (! empty($data['linked_business_id'])) {
            $linked = User::query()->where('type', User::TYPE_BUSINESS)->find((int) $data['linked_business_id']);
            if (! $linked || (int) $linked->id === $businessId) {
                throw ValidationException::withMessages(['linked_business_id' => __('النشاط المورّد غير موجود.')]);
            }
        }

        if ($supplier) {
            $supplier->update($data);

            return $supplier->fresh();
        }

        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();
        if ($existing = Supplier::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing;
        }

        return Supplier::create(['uuid' => $uuid, 'business_id' => $businessId] + $data);
    }

    /** What the business owes this supplier: bills − payments − returns. */
    public function balance(Supplier $supplier): float
    {
        $bills = (float) SupplierBill::query()->where('supplier_id', $supplier->id)->sum('amount');
        $paid = (float) SupplierPayment::query()->where('supplier_id', $supplier->id)->sum('amount');
        $returned = (float) SupplierReturn::query()->where('supplier_id', $supplier->id)->sum('total');

        return round($bills - $paid - $returned, 2);
    }

    // ── purchase orders ──────────────────────────────────────────────────────────────────────────────────────────────

    public function createOrder(int $businessId, int $actorId, array $data): PurchaseOrder
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = PurchaseOrder::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing->load('lines');
        }

        $supplier = $this->supplier($businessId, (int) $data['supplier_id']);
        $warehouse = $this->access->warehouse($businessId, $data['warehouse_id'] ?? null);

        return DB::transaction(function () use ($businessId, $actorId, $data, $uuid, $supplier, $warehouse) {
            $order = PurchaseOrder::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'number' => $this->nextNumber($businessId, 'next_po_number'),
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'status' => PurchaseOrder::DRAFT,
                'expected_on' => $data['expected_on'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);
            $this->writeLines($order, $data['lines']);

            return $order->fresh('lines');
        });
    }

    public function updateOrder(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::DRAFT) {
            throw ValidationException::withMessages(['status' => __('لا يُعدَّل أمر الشراء إلا وهو مسودة.')]);
        }

        return DB::transaction(function () use ($order, $data) {
            if (isset($data['supplier_id'])) {
                $order->supplier_id = $this->supplier((int) $order->business_id, (int) $data['supplier_id'])->id;
            }
            if (array_key_exists('warehouse_id', $data)) {
                $order->warehouse_id = $this->access->warehouse((int) $order->business_id, $data['warehouse_id'])->id;
            }
            foreach (['expected_on', 'notes'] as $key) {
                if (array_key_exists($key, $data)) {
                    $order->{$key} = $data[$key];
                }
            }
            $order->save();

            if (isset($data['lines'])) {
                $order->lines()->delete();
                $this->writeLines($order, $data['lines']);
            }

            return $order->fresh('lines');
        });
    }

    /**
     * Send the draft on. Above the owner's limit, and from someone who may not approve, it waits for his approval (he
     * is told, and approves from his phone); otherwise it is approved at once and goes to the supplier.
     */
    public function submit(PurchaseOrder $order, User $actor, bool $actorMayApprove): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::DRAFT) {
            throw ValidationException::withMessages(['status' => __('أُرسل أمر الشراء من قبل.')]);
        }
        if (! $order->lines()->exists()) {
            throw ValidationException::withMessages(['lines' => __('أمر الشراء بلا أصناف.')]);
        }

        $limit = Setting::query()->where('business_id', $order->business_id)->value('po_approval_threshold');

        if ($limit !== null && (float) $order->total > (float) $limit && ! $actorMayApprove) {
            $order->update(['status' => PurchaseOrder::PENDING_APPROVAL]);
            $this->notify('inventory_po_pending_approval', (int) $order->business_id, $order,
                "أمر الشراء #{$order->number} بقيمة " . $this->money($order->total) . ' ج بانتظار اعتمادك.',
                "Purchase order #{$order->number} for " . $this->money($order->total) . ' EGP is waiting for your approval.');

            return $order->fresh('lines');
        }

        return $this->approveNow($order, (int) $actor->id);
    }

    public function approve(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => __('أمر الشراء ليس بانتظار الاعتماد.')]);
        }

        $order = $this->approveNow($order, $actorId);
        $this->tellCreator($order, 'اعتُمد أمر الشراء #' . $order->number . '.', 'Purchase order #' . $order->number . ' was approved.');

        return $order;
    }

    public function reject(PurchaseOrder $order, int $actorId, ?string $reason): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => __('أمر الشراء ليس بانتظار الاعتماد.')]);
        }

        $order->update(['status' => PurchaseOrder::REJECTED, 'approved_by' => $actorId, 'rejected_reason' => $reason]);
        $this->tellCreator($order, 'رُفض أمر الشراء #' . $order->number . ($reason ? ": {$reason}" : '.'), 'Purchase order #' . $order->number . ' was rejected.');

        return $order->fresh('lines');
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        $received = (float) $order->lines()->sum('received_qty') > 0;

        if ($received || ! in_array($order->status, [PurchaseOrder::DRAFT, PurchaseOrder::PENDING_APPROVAL, PurchaseOrder::APPROVED], true)) {
            throw ValidationException::withMessages(['status' => __('لا يُلغى أمر شراء استُلم منه شيء.')]);
        }

        $order->update(['status' => PurchaseOrder::CANCELLED]);

        return $order->fresh('lines');
    }

    public function close(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrder::PARTIALLY_RECEIVED, PurchaseOrder::RECEIVED], true)) {
            throw ValidationException::withMessages(['status' => __('يُغلق أمر الشراء بعد الاستلام فقط.')]);
        }

        $order->update(['status' => PurchaseOrder::CLOSED]);

        return $order->fresh('lines');
    }

    /** The supplier business (on BIM) acknowledges the order it received. */
    public function supplierConfirm(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, PurchaseOrder::RECEIVABLE, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تأكيد هذا الأمر الآن.')]);
        }

        if (! $order->supplier_confirmed_at) {
            $order->update(['supplier_confirmed_at' => now()]);
            $name = (string) optional(User::query()->find($order->supplier->linked_business_id))->name;
            $this->notify('inventory_po_decided', (int) $order->business_id, $order,
                "أكّد المورد {$name} أمر الشراء #{$order->number}.", "{$name} confirmed purchase order #{$order->number}.");
        }

        return $order->fresh('lines');
    }

    // ── receiving ────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Book a receipt: every line's good quantity enters the stock at its price (its damaged part stays out and remains
     * open on the order). A receipt is never edited afterwards — a mistake goes back as a return or a stocktake.
     */
    public function receive(int $businessId, int $actorId, array $data): Receipt
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = Receipt::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing->load('lines');
        }

        $order = null;
        if (! empty($data['purchase_order_id'])) {
            $order = PurchaseOrder::query()->where('business_id', $businessId)->lockForUpdate()->find((int) $data['purchase_order_id']);
            if (! $order) {
                throw ValidationException::withMessages(['purchase_order_id' => __('أمر الشراء غير موجود.')]);
            }
            if (! in_array($order->status, PurchaseOrder::RECEIVABLE, true)) {
                throw ValidationException::withMessages(['purchase_order_id' => __('لا يُستلم على أمر شراء غير معتمد أو مغلق.')]);
            }
        }

        $warehouse = $this->access->warehouse($businessId, $data['warehouse_id'] ?? ($order?->warehouse_id));

        return DB::transaction(function () use ($businessId, $actorId, $data, $uuid, $order, $warehouse) {
            $receipt = Receipt::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'number' => $this->nextNumber($businessId, 'next_receipt_number'),
                'purchase_order_id' => $order?->id,
                'supplier_id' => $order?->supplier_id ?? (! empty($data['supplier_id']) ? $this->supplier($businessId, (int) $data['supplier_id'])->id : null),
                'warehouse_id' => $warehouse->id,
                'user_id' => $actorId,
                'device_uuid' => $data['device_uuid'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_at' => isset($data['received_at']) ? Carbon::parse($data['received_at']) : now(),
            ]);

            $specs = [];

            foreach ($data['lines'] as $i => $row) {
                $poLine = null;
                if (! empty($row['po_line_id'])) {
                    $poLine = $order ? $order->lines()->find((int) $row['po_line_id']) : null;
                    if (! $poLine) {
                        throw ValidationException::withMessages(["lines.$i.po_line_id" => __('السطر ليس من أمر الشراء هذا.')]);
                    }
                }

                $item = $this->item($businessId, (int) ($row['item_id'] ?? $poLine?->item_id));
                $factor = (float) ($row['unit_factor'] ?? $poLine?->unit_factor ?? 1) ?: 1;
                $qty = (float) $row['qty'];
                $damaged = min($qty, (float) ($row['damaged_qty'] ?? 0));
                $price = (float) ($row['unit_price'] ?? $poLine?->unit_price ?? 0);
                $lot = $this->lot($item, $row['lot_code'] ?? null, $row['expires_on'] ?? null);

                $line = ReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'po_line_id' => $poLine?->id,
                    'item_id' => $item->id,
                    'unit_name' => (string) ($row['unit_name'] ?? $poLine?->unit_name ?? $item->base_unit),
                    'unit_factor' => $factor,
                    'qty' => $qty,
                    'damaged_qty' => $damaged,
                    'unit_price' => $price,
                    'lot_id' => $lot?->id,
                ]);

                if ($poLine) {
                    $poLine->increment('received_qty', $qty - $damaged);
                }

                if ($qty - $damaged > 0) {
                    $specs[] = [
                        'uuid' => $this->derivedUuid($uuid, 'line-' . $i),
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'lot_id' => $lot?->id,
                        'qty' => ($qty - $damaged) * $factor,
                        'unit_cost' => $price / $factor,
                        'type' => Move::RECEIPT,
                        'reference_type' => 'receipt',
                        'reference_id' => $receipt->id,
                        'device_uuid' => $data['device_uuid'] ?? null,
                        'user_id' => $actorId,
                        'occurred_at' => $receipt->received_at,
                    ];
                }
            }

            if ($specs !== []) {
                $this->ledger->postMany($businessId, $specs);
            }

            if ($order) {
                $open = $order->lines()->get()->contains(fn (PurchaseOrderLine $l) => $l->received_qty + 0.00001 < $l->qty);
                $order->update(['status' => $open ? PurchaseOrder::PARTIALLY_RECEIVED : PurchaseOrder::RECEIVED]);
            }

            return $receipt->fresh('lines');
        });
    }

    // ── the supplier's bill and the three-way match ──────────────────────────────────────────────────────────────────

    public function recordBill(int $businessId, array $data): SupplierBill
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = SupplierBill::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing->load('lines');
        }

        $supplier = $this->supplier($businessId, (int) $data['supplier_id']);
        $order = null;
        if (! empty($data['purchase_order_id'])) {
            $order = PurchaseOrder::query()->where('business_id', $businessId)->where('supplier_id', $supplier->id)->find((int) $data['purchase_order_id']);
            if (! $order) {
                throw ValidationException::withMessages(['purchase_order_id' => __('أمر الشراء غير موجود لهذا المورد.')]);
            }
        }

        return DB::transaction(function () use ($businessId, $data, $uuid, $supplier, $order) {
            $sum = 0.0;
            $bill = SupplierBill::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $order?->id,
                'number' => $data['number'] ?? null,
                'bill_date' => $data['bill_date'] ?? now()->toDateString(),
                'amount' => 0,
            ]);

            foreach ((array) ($data['lines'] ?? []) as $i => $row) {
                $poLine = $order?->lines()->find((int) $row['po_line_id']);
                if (! $poLine) {
                    throw ValidationException::withMessages(["lines.$i.po_line_id" => __('السطر ليس من أمر الشراء هذا.')]);
                }
                $price = (float) ($row['unit_price'] ?? $poLine->unit_price);
                SupplierBillLine::create(['bill_id' => $bill->id, 'po_line_id' => $poLine->id, 'qty' => (float) $row['qty'], 'unit_price' => $price]);
                $poLine->increment('billed_qty', (float) $row['qty']);
                $sum += (float) $row['qty'] * $price;
            }

            $bill->update(['amount' => isset($data['amount']) ? round((float) $data['amount'], 2) : round($sum, 2)]);

            return $bill->fresh('lines');
        });
    }

    /**
     * Ordered, received and billed side by side; every difference named. A bill asking more than was received, or at
     * another price than ordered, is not to be paid until it is settled.
     *
     * @return array{matched:bool,lines:list<array<string,mixed>>,ordered_total:float,received_total:float,billed_total:float}
     */
    public function match(PurchaseOrder $order): array
    {
        $billed = SupplierBillLine::query()
            ->whereIn('bill_id', SupplierBill::query()->where('purchase_order_id', $order->id)->pluck('id'))
            ->get()->groupBy('po_line_id');

        $lines = [];
        $matched = true;
        $totals = ['ordered_total' => 0.0, 'received_total' => 0.0, 'billed_total' => 0.0];

        foreach ($order->lines()->get() as $line) {
            $billedQty = (float) optional($billed->get($line->id))->sum('qty');
            $billedValue = (float) optional($billed->get($line->id))->sum(fn ($b) => $b->qty * $b->unit_price);
            $issues = [];

            if ($billedQty > $line->received_qty + 0.00001) {
                $issues[] = 'billed_more_than_received';
            }
            if ($billedQty > 0 && abs($billedValue / $billedQty - $line->unit_price) > 0.005) {
                $issues[] = 'price_differs';
            }

            $matched = $matched && $issues === [];
            $totals['ordered_total'] += $line->qty * $line->unit_price;
            $totals['received_total'] += $line->received_qty * $line->unit_price;
            $totals['billed_total'] += $billedValue;

            $lines[] = [
                'po_line_id' => $line->id,
                'item_id' => $line->item_id,
                'ordered' => $line->qty,
                'received' => $line->received_qty,
                'billed' => $billedQty,
                'order_price' => $line->unit_price,
                'billed_value' => round($billedValue, 2),
                'difference_value' => round($billedValue - $line->received_qty * $line->unit_price, 2),
                'issues' => $issues,
            ];
        }

        return ['matched' => $matched] + array_map(fn ($v) => round($v, 2), $totals) + ['lines' => $lines];
    }

    public function recordPayment(int $businessId, int $actorId, array $data): SupplierPayment
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = SupplierPayment::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing;
        }

        $supplier = $this->supplier($businessId, (int) $data['supplier_id']);
        if (! empty($data['bill_id']) && ! SupplierBill::query()->where('supplier_id', $supplier->id)->whereKey((int) $data['bill_id'])->exists()) {
            throw ValidationException::withMessages(['bill_id' => __('الفاتورة غير موجودة لهذا المورد.')]);
        }

        return SupplierPayment::create([
            'uuid' => $uuid,
            'business_id' => $businessId,
            'supplier_id' => $supplier->id,
            'bill_id' => $data['bill_id'] ?? null,
            'amount' => round((float) $data['amount'], 2),
            'method' => (string) $data['method'],
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'note' => $data['note'] ?? null,
            'user_id' => $actorId,
        ]);
    }

    /** Goods going back to the supplier at their average cost; the value is credited against what is owed to him. */
    public function returnToSupplier(int $businessId, int $actorId, array $data): SupplierReturn
    {
        $uuid = (string) ($data['uuid'] ?? '') ?: (string) Str::uuid();

        if ($existing = SupplierReturn::query()->where('uuid', $uuid)->where('business_id', $businessId)->first()) {
            return $existing;
        }

        $supplier = $this->supplier($businessId, (int) $data['supplier_id']);
        $warehouse = $this->access->warehouse($businessId, $data['warehouse_id'] ?? null);

        return DB::transaction(function () use ($businessId, $actorId, $data, $uuid, $supplier, $warehouse) {
            $return = SupplierReturn::create([
                'uuid' => $uuid,
                'business_id' => $businessId,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id' => $warehouse->id,
                'reason' => $data['reason'] ?? null,
                'user_id' => $actorId,
            ]);

            $total = 0.0;
            $specs = [];
            foreach ($data['lines'] as $i => $row) {
                $item = $this->item($businessId, (int) $row['item_id']);
                $cost = isset($row['unit_cost']) ? (float) $row['unit_cost'] : (float) $item->avg_cost;
                $total += (float) $row['qty'] * $cost;
                $specs[] = [
                    'uuid' => $this->derivedUuid($uuid, 'line-' . $i),
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'lot_id' => $row['lot_id'] ?? null,
                    'qty' => -1 * (float) $row['qty'],
                    'unit_cost' => $cost,
                    'type' => Move::SUPPLIER_RETURN,
                    'reason' => $data['reason'] ?? null,
                    'reference_type' => 'supplier_return',
                    'reference_id' => $return->id,
                    'user_id' => $actorId,
                ];
            }

            $this->ledger->postMany($businessId, $specs);
            $return->update(['total' => round($total, 2)]);

            return $return->fresh();
        });
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────────────────────

    private function approveNow(PurchaseOrder $order, int $actorId): PurchaseOrder
    {
        $order->update(['status' => PurchaseOrder::APPROVED, 'approved_by' => $actorId, 'approved_at' => now(), 'sent_at' => now()]);

        $supplier = $order->supplier()->first();
        if ($supplier && $supplier->linked_business_id) {
            $buyer = (string) optional(User::query()->find($order->business_id))->name;
            $this->notify('inventory_po_incoming', (int) $supplier->linked_business_id, $order,
                "وصلك أمر شراء #{$order->number} من {$buyer} بقيمة " . $this->money($order->total) . ' ج.',
                "Purchase order #{$order->number} from {$buyer}: " . $this->money($order->total) . ' EGP.');
        }

        return $order->fresh('lines');
    }

    /** @param  list<array<string,mixed>>  $lines */
    private function writeLines(PurchaseOrder $order, array $lines): void
    {
        $total = 0.0;

        foreach ($lines as $row) {
            $item = $this->item((int) $order->business_id, (int) $row['item_id']);
            $factor = (float) ($row['unit_factor'] ?? 1) ?: 1;
            $unitName = (string) ($row['unit_name'] ?? '') ?: $item->base_unit;

            if (! empty($row['unit_id'])) {
                $unit = $item->units()->find((int) $row['unit_id']);
                if (! $unit) {
                    throw ValidationException::withMessages(['lines' => __('وحدة الشراء ليست من وحدات الصنف.')]);
                }
                $factor = (float) $unit->factor;
                $unitName = (string) $unit->name;
            }

            PurchaseOrderLine::create([
                'purchase_order_id' => $order->id,
                'item_id' => $item->id,
                'unit_name' => $unitName,
                'unit_factor' => $factor,
                'qty' => (float) $row['qty'],
                'unit_price' => (float) $row['unit_price'],
            ]);
            $total += (float) $row['qty'] * (float) $row['unit_price'];
        }

        $order->update(['total' => round($total, 2)]);
    }

    private function supplier(int $businessId, int $id): Supplier
    {
        $supplier = Supplier::query()->where('business_id', $businessId)->find($id);

        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => __('المورد غير موجود.')]);
        }

        return $supplier;
    }

    private function item(int $businessId, int $id): Item
    {
        $item = Item::query()->where('business_id', $businessId)->find($id);

        if (! $item) {
            throw ValidationException::withMessages(['item_id' => __('الصنف غير موجود.')]);
        }

        return $item;
    }

    private function lot(Item $item, ?string $code, ?string $expiresOn): ?Lot
    {
        $code = trim((string) $code);

        if ($code === '' && ! $expiresOn) {
            return null;
        }

        $code = $code !== '' ? $code : 'EXP-' . Carbon::parse($expiresOn)->format('Ymd');
        $lot = Lot::query()->firstOrCreate(['item_id' => $item->id, 'code' => $code], ['expires_on' => $expiresOn]);

        if ($expiresOn && ! $lot->expires_on) {
            $lot->update(['expires_on' => $expiresOn]);
        }

        return $lot;
    }

    private function nextNumber(int $businessId, string $column): int
    {
        $this->access->settings($businessId);
        $row = Setting::query()->where('business_id', $businessId)->lockForUpdate()->first();
        $number = (int) $row->{$column};
        $row->update([$column => $number + 1]);

        return $number;
    }

    /** A stable uuid for a move derived from its document, so replaying the document books nothing twice. */
    private function derivedUuid(string $documentUuid, string $part): string
    {
        $hash = md5($documentUuid . '|' . $part);

        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4' . substr($hash, 13, 3) . '-a' . substr($hash, 17, 3) . '-' . substr($hash, 20, 12);
    }

    private function tellCreator(PurchaseOrder $order, string $ar, string $en): void
    {
        if ($order->created_by && (int) $order->created_by !== (int) $order->approved_by) {
            $this->notify('inventory_po_decided', (int) $order->created_by, $order, $ar, $en);
        }
    }

    private function notify(string $event, int $userId, PurchaseOrder $order, string $ar, string $en): void
    {
        try {
            app(NotificationDispatcherService::class)->dispatch($event, $userId, [
                'body_ar' => $ar,
                'body_en' => $en,
                'source_type' => 'inventory_purchase_order',
                'source_id' => $order->id,
                'meta' => ['purchase_order_id' => $order->id, 'business_id' => $order->business_id],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function money(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ','), '0'), '.');
    }
}
