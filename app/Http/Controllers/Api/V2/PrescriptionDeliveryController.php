<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\DeliveryDispatchService;
use App\Services\Prescriptions\PrescriptionDeliveryService;
use Illuminate\Http\Request;

/**
 * The connected delivery loop for prescriptions — same driver pool, same
 * shape as `DeliveryController` (menu orders). Registration/availability
 * toggle stay on `DeliveryController`; a driver doesn't register twice.
 */
final class PrescriptionDeliveryController extends Controller
{
    public function __construct(
        private readonly PrescriptionDeliveryService $delivery,
        private readonly DeliveryDispatchService $baseDelivery,
    ) {
    }

    /** GET /api/v2/delivery/available-prescriptions */
    public function available(Request $request)
    {
        $this->baseDelivery->driverOrFail((int) $request->user()->id);

        $rows = $this->delivery->availablePrescriptions()->map(fn (Prescription $p) => [
            'prescription_id' => (int) $p->id,
            'pharmacy' => $p->pharmacy ? ['id' => (int) $p->pharmacy->id, 'name' => (string) $p->pharmacy->name] : null,
            'delivery_address' => (string) $p->delivery_address,
            'medicine_total' => $p->medicine_total !== null ? (float) $p->medicine_total : null,
        ]);

        return response()->json(['success' => true, 'data' => ['prescriptions' => $rows]]);
    }

    /** POST /api/v2/delivery/prescriptions/{prescription}/accept */
    public function accept(Request $request, int $prescription)
    {
        $model = $this->delivery->acceptPrescription((int) $request->user()->id, $prescription);

        return response()->json(['success' => true, 'data' => [
            'prescription_id' => (int) $model->id,
            'delivery_stage' => (string) $model->delivery_stage,
        ]], 201);
    }

    /** POST /api/v2/delivery/prescriptions/{prescription}/pickup-token — pharmacy issues stage-1 token. */
    public function issuePickupToken(Request $request, int $prescription)
    {
        $model = Prescription::query()->findOrFail($prescription);
        $token = $this->delivery->issuePickupToken($model, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'prescription_id' => (int) $model->id,
            'pickup_token' => $token,
        ]]);
    }

    /** POST /api/v2/delivery/prescriptions/{prescription}/delivery-token — driver issues stage-2 token. */
    public function issueDeliveryToken(Request $request, int $prescription)
    {
        $model = $this->delivery->issueDeliveryToken($prescription, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'prescription_id' => (int) $model->id,
            'delivery_token' => (string) $model->delivery_token,
        ]]);
    }

    /** POST /api/v2/delivery/prescriptions/pickup/{token}/confirm — driver confirms pickup. */
    public function confirmPickup(Request $request, string $token)
    {
        $model = $this->delivery->confirmPickup($token, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'prescription_id' => (int) $model->id,
            'delivery_stage' => (string) $model->delivery_stage,
        ]]);
    }

    /** POST /api/v2/delivery/prescriptions/deliver/{token}/confirm — patient confirms receipt. */
    public function confirmDelivery(Request $request, string $token)
    {
        $model = $this->delivery->confirmDelivery($token, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'prescription_id' => (int) $model->id,
            'status' => (string) $model->status,
            'delivery_stage' => (string) $model->delivery_stage,
        ]]);
    }
}
