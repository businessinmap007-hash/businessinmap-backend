<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\Prescriptions\PrescriptionService;
use Illuminate\Http\Request;

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
            ->where('pharmacy_id', (int) $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->with(['items', 'doctor:id,name', 'patient:id,name'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (Prescription $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function prepare(Request $request, int $prescription)
    {
        return $this->act($request, $prescription, fn (Prescription $p) => $this->service->startPreparing($p), __('جارٍ تجهيز الوصفة.'));
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
            'data' => ['prescription' => $this->serialize($row->fresh(['items', 'doctor:id,name', 'patient:id,name']))],
        ]);
    }

    private function ownedOrFail(Request $request, int $id): Prescription
    {
        return Prescription::query()
            ->where('id', $id)
            ->where('pharmacy_id', (int) $request->user()->id)
            ->firstOrFail();
    }

    private function serialize(Prescription $p): array
    {
        return [
            'id' => (int) $p->id,
            'status' => (string) $p->status,
            'fulfillment_type' => $p->fulfillment_type,
            'delivery_address' => $p->delivery_address,
            'diagnosis' => $p->diagnosis,
            'notes' => $p->notes,
            'doctor' => $p->doctor ? ['id' => (int) $p->doctor->id, 'name' => $p->doctor->name] : ['id' => (int) $p->doctor_id],
            'patient' => $p->patient ? ['id' => (int) $p->patient->id, 'name' => $p->patient->name] : ['id' => (int) $p->patient_id],
            'items' => $p->relationLoaded('items')
                ? $p->items->map(fn ($i) => [
                    'name' => $i->name,
                    'dosage' => $i->dosage,
                    'quantity' => $i->quantity,
                    'instructions' => $i->instructions,
                ])->all()
                : [],
            'issued_at' => optional($p->issued_at)->toIso8601String(),
            'dispensed_at' => optional($p->dispensed_at)->toIso8601String(),
        ];
    }
}
