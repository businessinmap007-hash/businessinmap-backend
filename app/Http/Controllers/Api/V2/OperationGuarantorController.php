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
