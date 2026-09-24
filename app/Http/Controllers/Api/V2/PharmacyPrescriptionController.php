<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\Prescriptions\PrescriptionService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The pharmacy side of prescriptions. The `business` middleware guarantees the
 * caller is a business, so every action is scoped to prescriptions sent to the
 * calling pharmacy. Prepares → ready → dispenses, or rejects back to the patient.
 */
class PharmacyPrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $service)
    {
    }

    /** GET /api/v2/pharmacy/prescriptions — incoming, still open. */
    public function incoming(Request $request)
    {
        $rows = Prescription::query()
            ->where('pharmacy_id', BusinessContext::id($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->with(['items', 'doctor:id,name,name_en,medical_title', 'patient:id,name'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (Prescription $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * POST /api/v2/pharmacy/prescriptions/{prescription}/quote — reply to a
     * customer's direct request with real, dictionary-bound, priced items in
     * one shot. Nothing is prepared yet — the customer must accept first.
     */
    public function quote(Request $request, int $prescription)
    {
        $row = $this->ownedOrFail($request, $prescription);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $row = $this->service->quoteForCustomer($row, $data['items']);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال السعر إلى العميل.'),
            'data' => ['prescription' => $this->serialize($row->fresh(['items', 'patient:id,name']))],
        ]);
    }

    /** POST /api/v2/pharmacy/prescriptions/{prescription}/decline — cannot fulfil a direct request. */
    public function decline(Request $request, int $prescription)
    {
        $row = $this->ownedOrFail($request, $prescription);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $row = $this->service->declineRequest($row, $data['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('تم رفض الطلب.'),
            'data' => ['prescription' => $this->serialize($row->fresh(['patient:id,name']))],
        ]);
    }

    public function prepare(Request $request, int $prescription)
    {
        return $this->act($request, $prescription, fn (Prescription $p) => $this->service->startPreparing($p), __('جارٍ تجهيز الوصفة.'));
    }

    /**
     * POST /api/v2/pharmacy/prescriptions/{prescription}/price — the pharmacy
     * states its own price for every line at once (all-or-nothing invoice).
     */
    public function price(Request $request, int $prescription)
    {
        $row = $this->ownedOrFail($request, $prescription);
        $itemIds = $row->items()->pluck('id')->all();

        $data = $request->validate([
            'items' => ['required', 'array', 'size:' . count($itemIds)],
            'items.*.prescription_item_id' => ['required', 'integer', 'distinct', Rule::in($itemIds)],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.billed_quantity' => ['required', 'integer', 'min:1'],
        ]);

        $row = $this->service->price($row, $data['items']);

        return response()->json([
            'success' => true,
            'message' => __('تم تسعير الوصفة.'),
            'data' => ['prescription' => $this->serialize($row->fresh(['items', 'doctor:id,name,name_en,medical_title', 'patient:id,name']))],
        ]);
    }

    public function ready(Request $request, int $prescription)
    {
        return $this->act($request, $prescription, fn (Prescription $p) => $this->service->markReady($p), __('الوصفة جاهزة.'));
    }

    public function dispense(Request $request, int $prescription)
    {
        return $this->act($request, $prescription, fn (Prescription $p) => $this->service->dispense($p), __('تم صرف الوصفة.'));
    }

    public function reject(Request $request, int $prescription)
    {
        return $this->act($request, $prescription, fn (Prescription $p) => $this->service->reject($p), __('تم رفض الوصفة وإعادتها للمريض.'));
    }

    private function act(Request $request, int $prescriptionId, \Closure $action, string $message)
    {
        $row = $this->ownedOrFail($request, $prescriptionId);
        $row = $action($row);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['prescription' => $this->serialize($row->fresh(['items', 'doctor:id,name,name_en,medical_title', 'patient:id,name']))],
        ]);
    }

    private function ownedOrFail(Request $request, int $id): Prescription
    {
        return Prescription::query()
            ->where('id', $id)
            ->where('pharmacy_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    private function serialize(Prescription $p): array
    {
        return [
            'id' => (int) $p->id,
            'status' => (string) $p->status,
            'origin' => (string) ($p->origin ?: Prescription::ORIGIN_DOCTOR),
            'request_note' => $p->request_note,
            'fulfillment_type' => $p->fulfillment_type,
            'delivery_address' => $p->delivery_address,
            'diagnosis' => $p->diagnosis,
            'notes' => $p->notes,
            'doctor' => $p->doctor ? ['id' => (int) $p->doctor->id, 'name' => $p->doctor->displayName()] : null,
            'patient' => $p->patient ? ['id' => (int) $p->patient->id, 'name' => $p->patient->name] : ['id' => (int) $p->patient_id],
            'medicine_total' => $p->medicine_total !== null ? (float) $p->medicine_total : null,
            'priced_at' => optional($p->priced_at)->toIso8601String(),
            'images' => $p->imagePayload(),
            'items' => $p->relationLoaded('items')
                ? $p->items->map(fn ($i) => [
                    'id' => (int) $i->id,
                    'name' => $i->name,
                    'dosage' => $i->dosage,
                    'quantity' => $i->quantity,
                    'instructions' => $i->instructions,
                    'unit_price' => $i->unit_price !== null ? (float) $i->unit_price : null,
                    'billed_quantity' => $i->billed_quantity,
                    'line_total' => $i->line_total !== null ? (float) $i->line_total : null,
                ])->all()
                : [],
            'issued_at' => optional($p->issued_at)->toIso8601String(),
            'dispensed_at' => optional($p->dispensed_at)->toIso8601String(),
        ];
    }
}
