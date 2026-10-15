<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Services\Guarantees\OperationGuarantorService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Http\Request;

/**
 * Customer-facing co-guarantor flow (guarantee-as-deposit). The booking's
 * client invites a friend to supplement their guarantee coverage for one
 * operation; the friend accepts (freezing their coverage) or declines.
 * All endpoints are scoped to the authenticated user's role in the row.
 */
final class OperationGuarantorController extends Controller
{
    public function __construct(
        private readonly OperationGuarantorService $guarantors,
        private readonly NotificationDispatcherService $notifications,
    ) {
    }

    /** The booking's client lists its co-guarantors. */
    public function index(Request $request, int $booking)
    {
        $row = Booking::query()->findOrFail($booking);

        if ((int) $row->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => __('غير مصرح.')], 403);
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
        ], [], ['guarantor_user_id' => __('الصديق')]);

        $row = Booking::query()->findOrFail($booking);
        $user = $request->user();

        if ((int) $row->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => __('فقط صاحب الحجز يمكنه دعوة ضامن.')], 403);
        }

        $friend = User::query()->findOrFail((int) $data['guarantor_user_id']);

        $guarantor = $this->guarantors->invite(OperationGuarantor::OP_BOOKING, $booking, $user, $friend);

        // Notify the friend only when this is a fresh invite (invite() reuses an
        // existing pending/accepted row — don't re-ping on a duplicate request).
        if ((string) $guarantor->status === OperationGuarantor::STATUS_INVITED && $guarantor->wasRecentlyCreated) {
            $requesterName = trim((string) ($user->name ?? ''));

            $this->notify('coguarantor_invited', (int) $friend->id, $guarantor, [
                'actor_id' => (int) $user->id,
                'title_ar' => 'دعوة لمشاركتك في ضمان عملية',
                'title_en' => 'Co-guarantor request',
                'body_ar' => trim(($requesterName !== '' ? $requesterName . ' ' : '') . 'يطلب مشاركتك في ضمان عملية حجز.'),
                'body_en' => trim(($requesterName !== '' ? $requesterName . ': ' : '') . 'requested your guarantee for a booking.'),
                'action_type' => 'open_coguarantor_request',
                'action_url' => '/guarantors/' . $guarantor->id,
                'meta' => ['booking_id' => (int) $booking, 'requester_id' => (int) $user->id],
            ]);
        }

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($guarantor)]], 201);
    }

    // ── the same door for a health visit / an order / a trip reservation (anything the deposit core serves) ───────

    /** GET /api/v2/visit-deposits/{kind}/{subject}/guarantors — the requester lists his friends' answers. */
    public function operationIndex(Request $request, string $kind, int $subject)
    {
        $model = $this->operation($kind, $subject);
        $this->assertRequester($request, $model);
        $type = \App\Services\VisitDepositService::operationType($model);

        $items = OperationGuarantor::query()
            ->forOperation($type, (int) $model->getKey())
            ->with('guarantor:id,name,logo')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($g) => $this->present($g));

        return response()->json([
            'success' => true,
            'data' => [
                'guarantors' => $items,
                'combined_coverage' => $this->guarantors->combinedCoverage($type, (int) $model->getKey(), $request->user()),
                // what is still missing after his own guarantee, his friends' and his wallet
                'need' => app(\App\Services\VisitDepositService::class)->friendNeed($model),
            ],
        ]);
    }

    /** POST /api/v2/visit-deposits/{kind}/{subject}/guarantors — invite a friend (by id, phone or email). */
    public function operationInvite(Request $request, string $kind, int $subject)
    {
        $data = $request->validate([
            'guarantor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $model = $this->operation($kind, $subject);
        $user = $request->user();
        $this->assertRequester($request, $model);

        if (! $model->visitDepositOpen()) {
            return response()->json(['success' => false, 'message' => __('انتهت هذه العملية فلا يمكن دعوة ضامن لها.')], 422);
        }

        if (app(\App\Services\VisitDepositService::class)->friendNeed($model) === null) {
            return response()->json(['success' => false, 'message' => __('هذه العملية لا تطلب عربونًا على المنصة.')], 422);
        }

        $friend = null;

        if (! empty($data['guarantor_user_id'])) {
            $friend = User::query()->find((int) $data['guarantor_user_id']);
        } elseif (! empty($data['phone'])) {
            $friend = User::query()->where('phone', $data['phone'])->first();
        } elseif (! empty($data['email'])) {
            $friend = User::query()->where('email', $data['email'])->first();
        }

        if (! $friend) {
            return response()->json(['success' => false, 'message' => __('لا يوجد حساب بهذه البيانات.')], 422);
        }

        $type = \App\Services\VisitDepositService::operationType($model);
        $guarantor = $this->guarantors->invite($type, (int) $model->getKey(), $user, $friend);

        if ((string) $guarantor->status === OperationGuarantor::STATUS_INVITED && $guarantor->wasRecentlyCreated) {
            $requesterName = trim((string) ($user->name ?? ''));

            $this->notify('coguarantor_invited', (int) $friend->id, $guarantor, [
                'actor_id' => (int) $user->id,
                'title_ar' => 'دعوة لمشاركتك في ضمان عملية',
                'title_en' => 'Co-guarantor request',
                'body_ar' => trim(($requesterName !== '' ? $requesterName . ' ' : '') . 'يطلب مشاركتك في ضمان عملية.'),
                'body_en' => trim(($requesterName !== '' ? $requesterName . ': ' : '') . 'requested your guarantee for an operation.'),
                'action_type' => 'open_coguarantor_request',
                'action_url' => '/guarantors/' . $guarantor->id,
                'meta' => ['operation_type' => $type, 'operation_id' => (int) $model->getKey(), 'requester_id' => (int) $user->id],
            ]);
        }

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($guarantor)]], 201);
    }

    /** GET /api/v2/guarantors/{guarantor} — the invited friend (or the requester) reads one request. */
    public function show(Request $request, int $guarantor)
    {
        $row = OperationGuarantor::query()->with(['guarantor:id,name,logo', 'requester:id,name,logo'])->findOrFail($guarantor);
        $uid = (int) $request->user()->id;

        if (! in_array($uid, [(int) $row->guarantor_user_id, (int) $row->requester_user_id], true)) {
            return response()->json(['success' => false, 'message' => __('غير مصرح.')], 403);
        }

        $friend = User::query()->find($uid);

        return response()->json([
            'success' => true,
            'data' => [
                'guarantor' => $this->present($row) + [
                    'requester' => $row->requester ? ['id' => (int) $row->requester->id, 'name' => (string) $row->requester->name, 'logo' => $row->requester->logo] : null,
                    // what the friend can freeze right now
                    'available_coverage' => $friend ? $this->guarantors->usableCoverage($friend) : 0.0,
                ],
            ],
        ]);
    }

    /** The visit / order / reservation this door is about. */
    private function operation(string $kind, int $id): \Illuminate\Database\Eloquent\Model&\App\Contracts\HasVisitDeposit
    {
        $class = VisitDepositController::KINDS[$kind] ?? null;
        abort_if($class === null, 404);

        return $class::query()->findOrFail($id);
    }

    /** Only the customer of the operation may invite for it. A stranger must not learn it exists. */
    private function assertRequester(Request $request, \App\Contracts\HasVisitDeposit $model): void
    {
        abort_if($model->visitDepositClientId() !== (int) $request->user()->id, 404);
    }

    /** The invited friend accepts and freezes the given coverage. */
    public function accept(Request $request, int $guarantor)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ], [], ['amount' => __('قيمة التغطية')]);

        $row = $this->scopedForGuarantor($request, $guarantor);
        if ($row instanceof \Illuminate\Http\JsonResponse) {
            return $row;
        }

        $wasInvited = (string) $row->status === OperationGuarantor::STATUS_INVITED;

        $row = $this->guarantors->accept($row, (float) $data['amount']);

        // Notify the requester their friend accepted — only on the real
        // transition (accept() is idempotent on an already-accepted row).
        if ($wasInvited) {
            $friendName = trim((string) ($request->user()->name ?? ''));

            $this->notify('coguarantor_accepted', (int) $row->requester_user_id, $row, [
                'actor_id' => (int) $row->guarantor_user_id,
                'title_ar' => 'تم قبول طلب الضمان',
                'title_en' => 'Co-guarantor accepted',
                'body_ar' => trim(($friendName !== '' ? $friendName . ' ' : '') . 'قبل ضمان عمليتك بمبلغ ' . number_format((float) $row->covered_amount, 2) . '.'),
                'body_en' => trim(($friendName !== '' ? $friendName . ' ' : '') . 'accepted to guarantee your operation (' . number_format((float) $row->covered_amount, 2) . ').'),
                'action_type' => 'open_operation',
                'meta' => ['covered_amount' => (float) $row->covered_amount],
            ]);
        }

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($row)]]);
    }

    /** The invited friend declines. */
    public function decline(Request $request, int $guarantor)
    {
        $row = $this->scopedForGuarantor($request, $guarantor);
        if ($row instanceof \Illuminate\Http\JsonResponse) {
            return $row;
        }

        $wasInvited = (string) $row->status === OperationGuarantor::STATUS_INVITED;

        $row = $this->guarantors->decline($row);

        // Notify the requester their friend declined — only on the real
        // transition (decline() is a no-op on a non-invited row).
        if ($wasInvited) {
            $friendName = trim((string) ($request->user()->name ?? ''));

            $this->notify('coguarantor_declined', (int) $row->requester_user_id, $row, [
                'actor_id' => (int) $row->guarantor_user_id,
                'title_ar' => 'تم رفض طلب الضمان',
                'title_en' => 'Co-guarantor declined',
                'body_ar' => trim(($friendName !== '' ? $friendName . ' ' : '') . 'اعتذر عن ضمان عمليتك.'),
                'body_en' => trim(($friendName !== '' ? $friendName . ' ' : '') . 'declined to guarantee your operation.'),
                'action_type' => 'open_operation',
                'meta' => [],
            ]);
        }

        return response()->json(['success' => true, 'data' => ['guarantor' => $this->present($row)]]);
    }

    /** Fetch the row only if the authenticated user is its invited guarantor. */
    private function scopedForGuarantor(Request $request, int $guarantor): OperationGuarantor|\Illuminate\Http\JsonResponse
    {
        $row = OperationGuarantor::query()->findOrFail($guarantor);

        if ((int) $row->guarantor_user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => __('هذه الدعوة ليست موجهة إليك.')], 403);
        }

        return $row;
    }

    /**
     * Dispatch a co-guarantor notification through the full pipeline (in-app +
     * Firebase push, gated by the channel rule). Best-effort: a notification
     * failure must never break the invite/accept/decline API response. The row
     * is always attached as the notifiable + source so clients can deep-link
     * back to it; source_type carries the event key.
     */
    private function notify(string $eventKey, int $userId, OperationGuarantor $row, array $data): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, array_merge([
                'type' => AppNotification::TYPE_GUARANTEE,
                'notifiable_type' => OperationGuarantor::class,
                'notifiable_id' => (int) $row->id,
                'source_type' => $eventKey,
                'source_id' => (int) $row->id,
            ], $data));
        } catch (\Throwable $e) {
            report($e);
        }
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
