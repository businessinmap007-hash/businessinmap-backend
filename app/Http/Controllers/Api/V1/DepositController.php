<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\DepositsEscrowService;
use App\Enums\DepositStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepositController extends Controller
{
    public function __construct(private DepositsEscrowService $service) {}

    /**
     * إنشاء Deposit (جدية/ضمان للطرفين)
     */
    public function create(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|integer',
            'business_id' => 'required|integer|different:client_id',
            'total_amount' => 'required|numeric|min:0.01',

            // optional split
            'client_percent' => 'nullable|integer|min:0|max:100',
            'business_percent' => 'nullable|integer|min:0|max:100',

            // link to any target (order/service/etc)
            'target_type' => 'nullable|string|max:191',
            'target_id' => 'nullable|integer|min:0',
        ]);

        $clientPercent   = (int)($data['client_percent'] ?? 0);
        $businessPercent = (int)($data['business_percent'] ?? 0);

        $deposit = $this->service->create(
            (int)$data['client_id'],
            (int)$data['business_id'],
            $data['total_amount'],
            $clientPercent,
            $businessPercent,
            $data['target_type'] ?? null,
            isset($data['target_id']) ? (int)$data['target_id'] : null
        );

        return response()->json([
            'message' => 'Deposit created and frozen successfully.',
            'deposit' => $deposit
        ], 201);
    }

    /**
     * 🚀 Start Execution
     * - خصم رسوم الخدمة من مقدم الخدمة
     * - تُنفذ مرة واحدة فقط (Idempotent)
     */
    public function startExecution(Request $request, int $id)
    {
        $deposit = Deposit::findOrFail($id);

        // ✅ status is Enum (DepositStatus)
        if ($deposit->status !== DepositStatus::FROZEN) {
            return response()->json([
                'message' => 'Deposit must be frozen to start execution.',
            ], 422);
        }

        $fee = $this->service->chargeExecutionFee($deposit);

        return response()->json([
            'message' => 'Execution started and service fee charged.',
            'fee_amount' => $fee,
            'deposit' => $deposit->fresh(),
        ]);
    }

    /**
     * Release deposit: يرجع لكل طرف أمواله المحجوزة
     */
    public function release(Request $request, int $id)
    {
        $deposit = Deposit::findOrFail($id);
        $deposit = $this->service->release($deposit);

        return response()->json([
            'message' => 'Deposit released successfully.',
            'deposit' => $deposit
        ]);
    }

    /**
     * Refund deposit: إرجاع لطرف واحد أو للطرفين
     */
    public function refund(Request $request, int $id)
    {
        $data = $request->validate([
            'refund_client' => 'required|boolean',
            'refund_business' => 'required|boolean',
        ]);

        $deposit = Deposit::findOrFail($id);

        $deposit = $this->service->refund(
            $deposit,
            (bool)$data['refund_client'],
            (bool)$data['refund_business']
        );

        return response()->json([
            'message' => 'Deposit refunded successfully.',
            'deposit' => $deposit
        ]);
    }

    /**
     * عرض Deposit واحد
     */
    public function show(Request $request, int $id)
    {
        $deposit = Deposit::findOrFail($id);

        return response()->json([
            'deposit' => $deposit
        ]);
    }

    /**
     * قائمة Deposits (فلترة بسيطة)
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            // ✅ status comes as string from request, validate it
            'status' => ['nullable', 'string', Rule::in(DepositStatus::values())],
            'client_id' => 'nullable|integer',
            'business_id' => 'nullable|integer',
            'target_type' => 'nullable|string',
            'target_id' => 'nullable|integer',
        ]);

        $q = Deposit::query()->orderByDesc('id');

        if (!empty($data['status'])) {
            // ✅ DB stores enum value string (e.g. "frozen")
            $q->where('status', $data['status']);
        }

        if (!empty($data['client_id'])) {
            $q->where('client_id', (int)$data['client_id']);
        }

        if (!empty($data['business_id'])) {
            $q->where('business_id', (int)$data['business_id']);
        }

        if (!empty($data['target_type'])) {
            $q->where('target_type', $data['target_type']);
        }

        if (isset($data['target_id'])) {
            $q->where('target_id', (int)$data['target_id']);
        }

        return response()->json([
            'deposits' => $q->paginate(20)
        ]);
    }
}
