<?php

namespace App\Http\Controllers\Api\V2;

use App\Contracts\HasVisitDeposit;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\DisputeResource;
use App\Models\ClinicAppointment;
use App\Models\InvestigationOrder;
use App\Models\ProcedureRequest;
use App\Services\VisitDepositService;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «عربون الجدية» of a health visit — the business's answers and the dispute door (see VisitDepositService).
 *
 * The same three kinds of visit everywhere: a clinic appointment, a lab/radiology order, a hospital procedure.
 * Reading the deposit needs no endpoint of its own — each visit's payload carries `deposit`.
 */
class VisitDepositController extends Controller
{
    public const KINDS = [
        'clinic-appointment' => ClinicAppointment::class,
        'investigation-order' => InvestigationOrder::class,
        'procedure-request' => ProcedureRequest::class,
        'order' => \App\Models\Order::class,
    ];

    public function __construct(private readonly VisitDepositService $deposits)
    {
    }

    /** POST …/{subject}/deposit/confirm-received — the business got the deposit outside the platform. */
    public function confirmReceived(Request $request, int $subject, string $kind)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'transfer'])],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $row = $this->ownedSubject($request, $kind, $subject);
        $this->deposits->confirmReceived($row, BusinessContext::id($request), $data['method'], $data['reference'] ?? null);

        return $this->answer($row, __('تم تأكيد استلام العربون.'));
    }

    /** POST …/{subject}/deposit/waive — accepted without a deposit paid outside. */
    public function waive(Request $request, int $subject, string $kind)
    {
        $row = $this->ownedSubject($request, $kind, $subject);
        $this->deposits->waive($row, BusinessContext::id($request));

        return $this->answer($row, __('قُبل الموعد بلا عربون.'));
    }

    /** POST …/{subject}/deposit/give-back — the business returns a held deposit (after a no-show, for instance). */
    public function giveBack(Request $request, int $subject, string $kind)
    {
        $row = $this->ownedSubject($request, $kind, $subject);
        $this->deposits->giveBack($row, BusinessContext::id($request));

        return $this->answer($row, __('أُعيد العربون للطرفين.'));
    }

    /** POST /api/v2/visit-deposits/{kind}/{subject}/disputes — either party opens a dispute over the held deposit. */
    public function dispute(Request $request, string $kind, int $subject)
    {
        $model = $this->find($kind, $subject);
        $userId = (int) $request->user()->id;

        // a stranger must not learn the visit exists
        abort_if(
            $model->visitDepositClientId() !== $userId && $model->visitDepositBusinessId() !== $userId,
            404
        );

        $data = $request->validate([
            'reason_code' => ['required', 'string', Rule::in(DisputeController::REASON_CODES)],
            'reason_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispute = $this->deposits->openDispute(
            $model,
            $userId,
            $data['reason_text'] ?? null,
            $data['reason_code'],
        );

        $dispute->loadMissing(['openedBy:id,name', 'againstUser:id,name']);

        return response()->json(['success' => true, 'data' => new DisputeResource($dispute)]);
    }

    private function answer(Model $row, string $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['deposit' => $this->deposits->stateFor($row->fresh())],
        ]);
    }

    /** The visit, only when it is at the acting business. 404 otherwise — never confirm it exists. */
    private function ownedSubject(Request $request, string $kind, int $id): Model
    {
        $model = $this->find($kind, $id);

        abort_if($model->visitDepositBusinessId() !== BusinessContext::id($request), 404);

        return $model;
    }

    /** @return Model&HasVisitDeposit */
    private function find(string $kind, int $id): Model
    {
        $class = self::KINDS[$kind] ?? null;
        abort_if($class === null, 404);

        return $class::query()->findOrFail($id);
    }
}
