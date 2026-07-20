<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\DisputeResource;
use App\Models\Booking;
use App\Models\Dispute;
use App\Services\BookingDepositService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Disputes, from the point of view of a party to one.
 *
 * The whole mechanism — escrow freezing, warnings, escalation, rulings that
 * move money — existed with no way for the person with the grievance to reach
 * it: opening a dispute was admin-only or internal to BookingDepositService.
 * That is why the table was empty. This is the door in.
 *
 * Opening goes through BookingDepositService rather than DisputeService so it
 * cannot skip the checks that belong to the escrow: no dispute on a settled
 * deposit, no second dispute over one booking, and a `dispute_opened` event on
 * the deposit's own trail.
 *
 * Ruling is NOT here and must not be: a party deciding their own dispute is the
 * v1 /deposits hole in a new namespace. Resolution stays with the admin.
 */
final class DisputeController extends Controller
{
    /**
     * A controlled vocabulary, unlike the admin screens' free-text field. The
     * app needs stable keys to show a localized picker, and an arbitrator
     * needs to be able to group cases by what actually went wrong.
     */
    private const REASON_CODES = [
        'not_delivered',
        'not_as_described',
        'quality',
        'late',
        'cancelled_by_business',
        'no_show',
        'overcharged',
        'damage',
        'other',
    ];

    /** GET /api/v2/disputes/reason-codes — the picker. */
    public function reasonCodes()
    {
        return response()->json(['success' => true, 'data' => self::REASON_CODES]);
    }

    /** GET /api/v2/disputes — disputes this account is a party to. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                Dispute::STATUS_OPEN,
                Dispute::STATUS_MUTUAL_RESOLUTION,
                Dispute::STATUS_UNDER_REVIEW,
                Dispute::STATUS_RESOLVED,
                Dispute::STATUS_CLOSED,
                Dispute::STATUS_CANCELLED,
                Dispute::STATUS_EXPIRED,
            ])],
            'role' => ['nullable', 'in:opener,respondent'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $userId = (int) $request->user()->id;

        $disputes = Dispute::query()
            ->with(['openedBy:id,name', 'againstUser:id,name'])
            ->where(function ($w) use ($data, $userId) {
                // No filter parameter can widen this beyond the caller.
                match ($data['role'] ?? null) {
                    'opener' => $w->where('opened_by_user_id', $userId),
                    'respondent' => $w->where('against_user_id', $userId),
                    default => $w->where('opened_by_user_id', $userId)
                        ->orWhere('against_user_id', $userId),
                };
            })
            ->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->appends($request->query());

        return DisputeResource::collection($disputes);
    }

    /** GET /api/v2/disputes/{dispute} — one, if you are a party to it. */
    public function show(Request $request, Dispute $dispute)
    {
        $this->ensureParty($request, $dispute);

        $dispute->loadMissing(['openedBy:id,name', 'againstUser:id,name']);

        return response()->json(['success' => true, 'data' => new DisputeResource($dispute)]);
    }

    /** POST /api/v2/bookings/{booking}/disputes — open one on a booking. */
    public function storeForBooking(Request $request, Booking $booking, BookingDepositService $deposits)
    {
        $userId = (int) $request->user()->id;

        // The load-bearing check. Without it anyone signed in could open a
        // dispute over a stranger's booking and freeze their escrow — which is
        // exactly how v1 let anyone move anyone's deposit. 404 rather than 403:
        // a stranger must not learn that the booking exists.
        abort_if(
            (int) $booking->user_id !== $userId && (int) $booking->business_id !== $userId,
            404
        );

        $data = $request->validate([
            'reason_code' => ['required', 'string', Rule::in(self::REASON_CODES)],
            'reason_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispute = $deposits->openDisputeForBooking(
            booking: $booking,
            openedByUserId: $userId,
            actorId: $userId,
            payload: [
                'reason_code' => $data['reason_code'],
                'reason_text' => $data['reason_text'] ?? null,
                'source' => 'api_v2',
            ]
        );

        $dispute->loadMissing(['openedBy:id,name', 'againstUser:id,name']);

        // 200, not 201: an existing live dispute is returned rather than a
        // second one created, so the caller has not always created anything.
        return response()->json(['success' => true, 'data' => new DisputeResource($dispute)]);
    }

    private function ensureParty(Request $request, Dispute $dispute): void
    {
        $userId = (int) $request->user()->id;

        abort_if(
            (int) $dispute->opened_by_user_id !== $userId && (int) $dispute->against_user_id !== $userId,
            404
        );
    }
}
