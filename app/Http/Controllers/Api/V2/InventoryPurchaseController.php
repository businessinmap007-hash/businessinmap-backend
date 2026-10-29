<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Receipt;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\SupplierBill;
use App\Models\Inventory\SupplierPayment;
use App\Models\Inventory\SupplierReturn;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryPayload;
use App\Services\Inventory\PurchaseService;
use App\Support\BusinessCapability;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «المشتريات» — suppliers, purchase orders (with the owner's approval above his limit), receipts, the supplier's bill
 * matched three ways, payments recorded (never moved), returns. The supplier side — a business on BIM that received an
 * order — reads and confirms it under `incoming*`.
 */
final class InventoryPurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly InventoryAccess $access,
    ) {}

    // ── suppliers ────────────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/suppliers */
    public function suppliers(Request $request)
    {
        $this->feature($request);

        return $this->ok(['suppliers' => Supplier::query()->where('business_id', BusinessContext::id($request))->orderBy('name')->get()
            ->map(fn ($s) => InventoryPayload::supplier($s, $this->purchases->balance($s)))->values()]);
    }

    /** POST /business/inventory/suppliers */
    public function storeSupplier(Request $request)
    {
        $this->feature($request);
        $data = $request->validate($this->supplierRules(true));

        return $this->ok(['supplier' => InventoryPayload::supplier($this->purchases->saveSupplier(BusinessContext::id($request), $data))], 201);
    }

    /** PATCH /business/inventory/suppliers/{supplier} */
    public function updateSupplier(Request $request, int $supplier)
    {
        $model = $this->ownSupplier($request, $supplier);
        $data = $request->validate($this->supplierRules(false));

        return $this->ok(['supplier' => InventoryPayload::supplier($this->purchases->saveSupplier(BusinessContext::id($request), $data, $model))]);
    }

    /** GET /business/inventory/suppliers/{supplier} — with his account: orders, bills, payments, returns and the balance */
    public function showSupplier(Request $request, int $supplier)
    {
        $model = $this->ownSupplier($request, $supplier);

        return $this->ok([
            'supplier' => InventoryPayload::supplier($model, $this->purchases->balance($model)),
            'purchase_orders' => PurchaseOrder::query()->where('supplier_id', $model->id)->latest('id')->limit(50)->get()->map(fn ($p) => InventoryPayload::purchaseOrder($p))->values(),
            'bills' => SupplierBill::query()->where('supplier_id', $model->id)->with('lines')->latest('id')->get(),
            'payments' => SupplierPayment::query()->where('supplier_id', $model->id)->latest('id')->get(),
            'returns' => SupplierReturn::query()->where('supplier_id', $model->id)->latest('id')->get(),
        ]);
    }

    // ── purchase orders ──────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/inventory/purchase-orders?status= */
    public function index(Request $request)
    {
        $this->feature($request);
        $data = $request->validate(['status' => ['nullable', 'string', 'max:24']]);

        $orders = PurchaseOrder::query()->where('business_id', BusinessContext::id($request))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->with('lines', 'supplier')->latest('id')->paginate(30);

        return response()->json(['success' => true, 'data' => ['purchase_orders' => collect($orders->items())->map(fn ($p) => InventoryPayload::purchaseOrder($p))->values()],
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()]]);
    }

    /** POST /business/inventory/purchase-orders — a draft; `submit: true` sends it on in the same call */
    public function store(Request $request)
    {
        $this->feature($request);
        $data = $request->validate($this->orderRules(true) + ['submit' => ['sometimes', 'boolean']]);
        $order = $this->purchases->createOrder(BusinessContext::id($request), (int) $request->user()->id, $data);

        if (($data['submit'] ?? false) && $order->status === PurchaseOrder::DRAFT) {
            $order = $this->purchases->submit($order, $request->user(), $this->mayApprove($request));
        }

        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($order)], 201);
    }

    /** GET /business/inventory/purchase-orders/{order} — with its receipts and the three-way match */
    public function show(Request $request, int $order)
    {
        $model = $this->ownOrder($request, $order);

        return $this->ok([
            'purchase_order' => InventoryPayload::purchaseOrder($model),
            'receipts' => Receipt::query()->where('purchase_order_id', $model->id)->with('lines')->get()->map(fn ($r) => InventoryPayload::receipt($r))->values(),
            'match' => $this->purchases->match($model),
            'can_approve' => $this->mayApprove($request),
        ]);
    }

    /** PATCH /business/inventory/purchase-orders/{order} — drafts only */
    public function update(Request $request, int $order)
    {
        $data = $request->validate($this->orderRules(false));

        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->updateOrder($this->ownOrder($request, $order), $data))]);
    }

    /** POST /business/inventory/purchase-orders/{order}/submit */
    public function submit(Request $request, int $order)
    {
        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->submit($this->ownOrder($request, $order), $request->user(), $this->mayApprove($request)))]);
    }

    /** POST /business/inventory/purchase-orders/{order}/approve — the owner, or a staff member given «اعتماد أوامر الشراء» */
    public function approve(Request $request, int $order)
    {
        $this->approverOnly($request);

        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->approve($this->ownOrder($request, $order), (int) $request->user()->id))]);
    }

    /** POST /business/inventory/purchase-orders/{order}/reject — {reason?} */
    public function reject(Request $request, int $order)
    {
        $this->approverOnly($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->reject($this->ownOrder($request, $order), (int) $request->user()->id, $data['reason'] ?? null))]);
    }

    /** POST /business/inventory/purchase-orders/{order}/cancel */
    public function cancel(Request $request, int $order)
    {
        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->cancel($this->ownOrder($request, $order)))]);
    }

    /** POST /business/inventory/purchase-orders/{order}/close */
    public function close(Request $request, int $order)
    {
        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->close($this->ownOrder($request, $order)))]);
    }

    // ── receipts, bills, payments, returns ───────────────────────────────────────────────────────────────────────────

    /** POST /business/inventory/receipts */
    public function receive(Request $request)
    {
        $this->feature($request);
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'purchase_order_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'device_uuid' => ['nullable', 'uuid'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.po_line_id' => ['nullable', 'integer'],
            'lines.*.item_id' => ['required_without:lines.*.po_line_id', 'nullable', 'integer'],
            'lines.*.unit_name' => ['nullable', 'string', 'max:40'],
            'lines.*.unit_factor' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.damaged_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.lot_code' => ['nullable', 'string', 'max:64'],
            'lines.*.expires_on' => ['nullable', 'date'],
        ]);

        $lotted = collect($data['lines'])->contains(fn ($l) => ! empty($l['lot_code']) || ! empty($l['expires_on']));
        if ($lotted) {
            $this->access->assertFeature(BusinessContext::business($request), InventoryAccess::LOTS);
        }

        $receipt = $this->purchases->receive(BusinessContext::id($request), (int) $request->user()->id, $data);

        return $this->ok(['receipt' => InventoryPayload::receipt($receipt)], 201);
    }

    /** POST /business/inventory/supplier-bills */
    public function storeBill(Request $request)
    {
        $this->feature($request);
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'supplier_id' => ['required', 'integer'],
            'purchase_order_id' => ['nullable', 'integer'],
            'number' => ['nullable', 'string', 'max:60'],
            'bill_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required_with:purchase_order_id', 'array'],
            'lines.*.po_line_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $bill = $this->purchases->recordBill(BusinessContext::id($request), $data);
        $order = $bill->purchase_order_id ? PurchaseOrder::query()->find($bill->purchase_order_id) : null;

        return $this->ok(['bill' => $bill, 'match' => $order ? $this->purchases->match($order) : null], 201);
    }

    /** POST /business/inventory/supplier-payments — RECORDED only: the money moved outside the app */
    public function storePayment(Request $request)
    {
        $this->feature($request);
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'supplier_id' => ['required', 'integer'],
            'bill_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'cheque', 'other'])],
            'paid_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $payment = $this->purchases->recordPayment(BusinessContext::id($request), (int) $request->user()->id, $data);
        $supplier = Supplier::query()->find($payment->supplier_id);

        return $this->ok(['payment' => $payment, 'balance' => $this->purchases->balance($supplier)], 201);
    }

    /** POST /business/inventory/supplier-returns */
    public function storeReturn(Request $request)
    {
        $this->feature($request);
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'supplier_id' => ['required', 'integer'],
            'purchase_order_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.lot_id' => ['nullable', 'integer'],
        ]);

        $return = $this->purchases->returnToSupplier(BusinessContext::id($request), (int) $request->user()->id, $data);

        return $this->ok(['return' => $return, 'balance' => $this->purchases->balance(Supplier::query()->find($return->supplier_id))], 201);
    }

    // ── the supplier side ────────────────────────────────────────────────────────────────────────────────────────────

    /** GET /business/incoming-purchase-orders — orders other businesses sent to THIS business as their supplier */
    public function incoming(Request $request)
    {
        $orders = PurchaseOrder::query()
            ->whereIn('supplier_id', Supplier::query()->where('linked_business_id', BusinessContext::id($request))->pluck('id'))
            ->whereNotIn('status', [PurchaseOrder::DRAFT, PurchaseOrder::PENDING_APPROVAL, PurchaseOrder::REJECTED])
            ->with('lines', 'supplier')->latest('id')->limit(100)->get();

        return $this->ok(['purchase_orders' => $orders->map(fn ($p) => InventoryPayload::purchaseOrder($p, true))->values()]);
    }

    /** POST /business/incoming-purchase-orders/{order}/confirm */
    public function confirmIncoming(Request $request, int $order)
    {
        $model = PurchaseOrder::query()
            ->whereIn('supplier_id', Supplier::query()->where('linked_business_id', BusinessContext::id($request))->pluck('id'))
            ->whereNotIn('status', [PurchaseOrder::DRAFT, PurchaseOrder::PENDING_APPROVAL, PurchaseOrder::REJECTED])
            ->findOrFail($order);

        return $this->ok(['purchase_order' => InventoryPayload::purchaseOrder($this->purchases->supplierConfirm($model), true)]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────────────────────

    private function feature(Request $request): void
    {
        $this->access->assertFeature(BusinessContext::business($request), InventoryAccess::PURCHASES);
    }

    private function mayApprove(Request $request): bool
    {
        return BusinessContext::isOwner($request) || in_array(BusinessCapability::PURCHASE_APPROVAL, BusinessContext::capabilities($request), true);
    }

    private function approverOnly(Request $request): void
    {
        if (! $this->mayApprove($request)) {
            abort(response()->json(['success' => false, 'message' => __('لا تملك صلاحية اعتماد أوامر الشراء.')], 403));
        }
    }

    private function ownOrder(Request $request, int $id): PurchaseOrder
    {
        return PurchaseOrder::query()->where('business_id', BusinessContext::id($request))->with('lines', 'supplier')->findOrFail($id);
    }

    private function ownSupplier(Request $request, int $id): Supplier
    {
        return Supplier::query()->where('business_id', BusinessContext::id($request))->findOrFail($id);
    }

    private function supplierRules(bool $creating): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'linked_business_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function orderRules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'uuid' => ['nullable', 'uuid'],
            'supplier_id' => [$req, 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => [$req, 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.unit_id' => ['nullable', 'integer'],
            'lines.*.unit_name' => ['nullable', 'string', 'max:40'],
            'lines.*.unit_factor' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    private function ok(array $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
