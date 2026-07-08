<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Services\Guarantees\OperationGuarantorService;
use Illuminate\Http\Request;

/**
 * Customer-facing co-guarantor flow (guarantee-as-deposit). The booking's
 * client invites a friend to supplement their guarantee coverage for one
 * operation; the friend accepts (freezing their coverage) or declines.
 * All endpoints are scoped to the authenticated user's role in the row.
 */
final class OperationGuarantorController extends Controller
{
    public function __construct(private readonly OperationGuarantorService $guarantors)
    {
    }

    /** The booking's client lists its co-guarantors. */
    public function index(Request $request, int $booking)
    {
        $row = Booking::query()->findOrFail($booking);

        if ((int) $row->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        $items = OperationGuarantor::query()
            ->forOperation(OperationGuarantor::OP_BOOKING, $booking)
            ->with('guarantor:id,name,logo')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($g) => $this->present($g));

        return response()->json([
            'success' => true,
            'data' => [
                'guarantors' => $items,
                'combined_coverage' => $this->guarantors->combinedCoverage(
                    OperationGuarantor::OP_BOOKING,
                    $booking,
                    $row->user ?? $request->user()
                ),
            ],
        ]);
    }

    /** The client invites a friend to co-guarantee this booking. */
    public function invite(Request $request, int $booking)
    {
        $data = $request->validate([
            'guarantor_user_id' => ['required', 'integer', 'exists:users,id'],
        ], [], ['guarantor_user_id' => 'الصديق']);

        $row = Booking::query()->findOrFail($booking);
        $user = $request->user();

        if ((int) $row->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'فقط صاحب الحجز يمكنه دعوة ضامن.'], 403);
        }

        $friend = User::query()->findOrFail((int) $data['guarantor_user_id']);

        $guarantor = $this->guarantors->invite(OperationGuarantor::OP_BOOKING, $booking, $user, $friend);

        // TODO(notifications): notify $friend of the pending co-guarantor request.

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($guarantor)]], 201);
    }

    /** The invited friend accepts and freezes the given coverage. */
    public function accept(Request $request, int $guarantor)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ], [], ['amount' => 'قيمة التغطية']);

        $row = $this->scopedForGuarantor($request, $guarantor);
        if ($row instanceof \Illuminate\Http\JsonResponse) {
            return $row;
        }

        $row = $this->guarantors->accept($row, (float) $data['amount']);

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($row)]]);
    }

    /** The invited friend declines. */
    public function decline(Request $request, int $guarantor)
    {
        $row = $this->scopedForGuarantor($request, $guarantor);
        if ($row instanceof \Illuminate\Http\JsonResponse) {
            return $row;
        }

        $row = $this->guarantors->decline($row);

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($row)]]);
    }

    /** Fetch the row only if the authenticated user is its invited guarantor. */
    private function scopedForGuarantor(Request $request, int $guarantor): OperationGuarantor|\Illuminate\Http\JsonResponse
    {
        $row = OperationGuarantor::query()->findOrFail($guarantor);

        if ((int) $row->guarantor_user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'هذه الدعوة ليست موجهة إليك.'], 403);
        }

        return $row;
    }

    private function present(OperationGuarantor $g): array
    {
        return [
            'id' => (int) $g->id,
            'operation_type' => (string) $g->operation_type,
            'operation_id' => (int) $g->operation_id,
            'guarantor' => $g->relationLoaded('guarantor') && $g->guarantor ? [
                'id' => (int) $g->guarantor->id,
                'name' => (string) $g->guarantor->name,
                'logo' => $g->guarantor->logo,
            ] : ['id' => (int) $g->guarantor_user_id],
            'covered_amount' => (float) $g->covered_amount,
            'status' => (string) $g->status,
        ];
    }
}
