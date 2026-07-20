<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\DisputeService;
use App\Services\Integrations\BookingGuaranteeIntegration;
use App\Models\GuaranteeLevel;
use App\Services\Guarantees\GuaranteePenaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function __construct(
        protected DisputeService $disputeService,
        protected BookingGuaranteeIntegration $bookingGuaranteeIntegration,
        protected GuaranteePenaltyService $guaranteePenaltyService,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $sort = (string) $request->get('sort', 'id');
        $dir  = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id',
            'status',
            'opened_at',
            'resolved_at',
            'closed_at',
            'created_at',
            'updated_at',
            'opened_by_user_id',
            'against_user_id',
            'platform_service_id',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $q                 = trim((string) $request->get('q', ''));
        $status            = trim((string) $request->get('status', ''));
        $platformServiceId = $request->filled('platform_service_id') ? (int) $request->get('platform_service_id') : null;
        $openedByUserId    = $request->filled('opened_by_user_id') ? (int) $request->get('opened_by_user_id') : null;
        $againstUserId     = $request->filled('against_user_id') ? (int) $request->get('against_user_id') : null;

        $disputes = Dispute::query()
            ->with([
                'platformService',
                'openedBy:id,name,email',
                'againstUser:id,name,email',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('id', $q)
                        ->orWhere('reason_code', 'like', "%{$q}%")
                        ->orWhere('reason_text', 'like', "%{$q}%")
                        ->orWhere('resolution_type', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($platformServiceId, fn ($query) => $query->where('platform_service_id', $platformServiceId))
            ->when($openedByUserId, fn ($query) => $query->where('opened_by_user_id', $openedByUserId))
            ->when($againstUserId, fn ($query) => $query->where('against_user_id', $againstUserId))
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        $platformServices = PlatformService::query()
            ->orderBy('name_en')
            ->get(['id', 'key', 'name_ar', 'name_en']);

        $users = User::query()
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'email']);

        $statuses = $this->statusOptions();

        return view('admin-v2.disputes.index', compact(
            'disputes',
            'platformServices',
            'users',
            'statuses',
            'q',
            'status',
            'platformServiceId',
            'openedByUserId',
            'againstUserId',
            'perPage',
            'sort',
            'dir'
        ));
    }

    public function show(Dispute $dispute)
    {
        $dispute->load([
            'platformService',
            'openedBy',
            'againstUser',
        ]);

        $disputeable = $this->resolveDisputeable($dispute);

        if ($disputeable instanceof Booking) {
            $disputeable->loadMissing([
                'user',
                'business',
                'service',
                'latestDeposit',
            ]);
        }

        // Read the room without taking a seat: an admin should be able to look
        // at a case before deciding to arbitrate it, and joining announces
        // itself to both parties.
        $thread = $this->disputeService->room($dispute);
        $thread->load(['participants.user:id,name', 'messages.sender:id,name']);

        $session = \App\Models\ArbitrationSession::query()->where('dispute_id', $dispute->id)->first();
        $violations = app(\App\Services\ThreadService::class)->violations($thread);

        return view('admin-v2.disputes.show', compact('dispute', 'disputeable', 'thread', 'session', 'violations'));
    }

    /**
     * Accept the case on stated terms, then take the seat.
     *
     * The fee is fixed before anything is heard and announced to both parties;
     * setting it afterwards would let the price of a ruling be adjusted to the
     * ruling.
     */
    public function acceptSession(Request $request, Dispute $dispute)
    {
        try {
            // No price to submit: it is platform policy per service, read from
            // the dispute-fees screen. See ArbitrationService::acceptSession().
            app(\App\Services\ArbitrationService::class)->acceptSession(
                dispute: $dispute,
                arbitratorId: (int) auth()->id()
            );

            $this->disputeService->joinAsArbitrator($dispute, (int) auth()->id());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر قبول الجلسة: ') . $e->getMessage());
        }

        return back()->with('success', __('تم قبول الجلسة وإعلام الطرفين برسم التحكيم.'));
    }

    /**
     * Record a conduct violation against a party.
     *
     * Recording is all it does — no automatic loss and no automatic fine. The
     * charter a party accepted is consent to the arbitrator's JUDGEMENT, not to
     * a machine deciding what counts as an insult; the consequence is applied
     * through the ordinary ruling and fine controls, with this on the record.
     */
    public function recordConductViolation(Request $request, Dispute $dispute)
    {
        $data = $request->validate([
            'against_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'thread_message_id' => ['nullable', 'integer'],
        ]);

        try {
            $thread = $this->disputeService->joinAsArbitrator($dispute, (int) auth()->id());

            app(\App\Services\ThreadService::class)->recordViolation(
                thread: $thread,
                againstUserId: (int) $data['against_user_id'],
                recordedByUserId: (int) auth()->id(),
                reason: $data['reason'],
                messageId: isset($data['thread_message_id']) ? (int) $data['thread_message_id'] : null
            );
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر تسجيل المخالفة: ') . $e->getMessage());
        }

        return back()->with('success', __('تم تسجيل مخالفة السلوك.'));
    }

    /**
     * Order one party to compensate the other — a real cost the escrow does not
     * cover, like shipping already paid on an order that was refused.
     */
    public function awardCompensation(Request $request, Dispute $dispute)
    {
        $data = $request->validate([
            'compensation_to' => ['required', 'in:client,business'],
            'compensation_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'compensation_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $session = app(\App\Services\ArbitrationService::class)->awardCompensation(
                dispute: $dispute,
                toSide: $data['compensation_to'],
                amount: (float) $data['compensation_amount'],
                note: $data['compensation_note'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر الحكم بالتعويض: ') . $e->getMessage());
        }

        return back()->with(
            'success',
            $session->compensation_paid_at
                ? __('تم الحكم بالتعويض وتحويله.')
                : __('تم الحكم بالتعويض، ولم يُسدَّد بعد لعدم كفاية رصيد الطرف الملزم.')
        );
    }

    /** Retry an ordered compensation the payer could not afford at the time. */
    public function settleCompensation(Dispute $dispute)
    {
        $session = app(\App\Services\ArbitrationService::class)->settleCompensation($dispute);

        return back()->with(
            $session->compensation_paid_at ? 'success' : 'error',
            $session->compensation_paid_at ? __('تم تحويل التعويض.') : __('ما زال رصيد الطرف الملزم غير كافٍ.')
        );
    }

    /** Take the arbitrator's seat in the dispute's room, and speak in it. */
    public function roomPost(Request $request, Dispute $dispute)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $thread = $this->disputeService->joinAsArbitrator($dispute, (int) auth()->id());

            app(\App\Services\ThreadService::class)
                ->post($thread, (int) auth()->id(), $data['body']);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر إرسال الرسالة: ') . $e->getMessage());
        }

        return back()->with('success', __('تم إرسال الرسالة إلى غرفة النزاع.'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform_service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'disputeable_type'    => ['required', 'string', 'max:255'],
            'disputeable_id'      => ['required', 'integer'],
            'opened_by_user_id'   => ['required', 'integer', 'exists:users,id'],
            'against_user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'reason_code'         => ['nullable', 'string', 'max:100'],
            'reason_text'         => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $dispute = DB::transaction(function () use ($data) {
                return $this->disputeService->open(
                    platformServiceId: (int) $data['platform_service_id'],
                    disputeableType: $data['disputeable_type'],
                    disputeableId: (int) $data['disputeable_id'],
                    openedByUserId: (int) $data['opened_by_user_id'],
                    againstUserId: isset($data['against_user_id']) ? (int) $data['against_user_id'] : null,
                    actorId: auth()->id(),
                    payload: [
                        'reason_code' => $data['reason_code'] ?? null,
                        'reason_text' => $data['reason_text'] ?? null,
                    ]
                );
            });

            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->with('success', __('تم فتح النزاع بنجاح.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('تعذر فتح النزاع: ') . $e->getMessage());
        }
    }

    public function openForBooking(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:100'],
            'reason_text' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $dispute = DB::transaction(function () use ($booking, $data) {
                $dispute = $this->disputeService->openForBooking(
                    booking: $booking,
                    openedByUserId: auth()->id() ?: (int) $booking->user_id,
                    actorId: auth()->id(),
                    payload: [
                        'reason_code' => $data['reason_code'] ?? null,
                        'reason_text' => $data['reason_text'] ?? null,
                    ]
                );

                $this->bookingGuaranteeIntegration->recordDisputeOpened($booking);

                return $dispute;
            });

            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->with('success', __('تم فتح نزاع على الحجز بنجاح.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر فتح نزاع على الحجز: ') . $e->getMessage());
        }
    }

    public function setUnderReview(Dispute $dispute)
    {
        // `mutual_resolution` belongs here: it is the status every dispute is
        // actually opened in, so without it an admin could not escalate a
        // single real dispute by hand — only the ones no code path creates.
        $this->ensureDisputeStatus($dispute, ['open', 'mutual_resolution']);

        $dispute->status = 'under_review';
        if (Schema::hasColumn('disputes', 'opened_at') && empty($dispute->opened_at)) {
            $dispute->opened_at = now();
        }
        $dispute->save();

        return back()->with('success', __('تم تحويل النزاع إلى قيد المراجعة.'));
    }

    public function cancel(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review']);

        $dispute->status = 'cancelled';
        if (Schema::hasColumn('disputes', 'closed_at')) {
            $dispute->closed_at = now();
        }
        $dispute->save();

        return back()->with('success', __('تم إلغاء النزاع.'));
    }

    public function close(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['resolved']);

        $dispute->status = 'closed';
        if (Schema::hasColumn('disputes', 'closed_at')) {
            $dispute->closed_at = now();
        }
        $dispute->save();

        return back()->with('success', __('تم إغلاق النزاع.'));
    }

    public function resolveReleaseBusiness(Request $request, Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review', 'mutual_resolution']);

        $data = $request->validate([
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fine_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fine_on' => ['nullable', 'in:client,business'],
            'platform_fine_reason' => ['nullable', 'in:conduct,non_compliance'],
            'charge_arbitration_fee' => ['nullable', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($dispute, $data) {
                $resolved = $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'release_business',
                    resolutionPayload: [
                        'penalty_amount' => (float) ($data['penalty_amount'] ?? 0),
                    ],
                    actorId: auth()->id()
                );

                $this->recordBookingDisputeResult($resolved, 'release_business');

                $this->applyPlatformFineIfNeeded($resolved, $data);

                $this->chargeArbitrationFeeIfSet($resolved, $data);

                $this->applyGuaranteePenaltyIfNeeded(
                    dispute: $resolved,
                    loserSide: 'client',
                    amount: (float) ($data['penalty_amount'] ?? 0),
                    reason: 'Dispute resolved in favor of business'
                );
            });

            return back()->with('success', __('تم حل النزاع لصالح مقدم الخدمة.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر حل النزاع: ') . $e->getMessage());
        }
    }

    public function resolveRefundClient(Request $request, Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review', 'mutual_resolution']);

        $data = $request->validate([
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fine_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fine_on' => ['nullable', 'in:client,business'],
            'platform_fine_reason' => ['nullable', 'in:conduct,non_compliance'],
            'charge_arbitration_fee' => ['nullable', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($dispute, $data) {
                $resolved = $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'refund_client',
                    resolutionPayload: [
                        'penalty_amount' => (float) ($data['penalty_amount'] ?? 0),
                    ],
                    actorId: auth()->id()
                );

                $this->recordBookingDisputeResult($resolved, 'refund_client');

                $this->applyPlatformFineIfNeeded($resolved, $data);

                $this->chargeArbitrationFeeIfSet($resolved, $data);

                $this->applyGuaranteePenaltyIfNeeded(
                    dispute: $resolved,
                    loserSide: 'business',
                    amount: (float) ($data['penalty_amount'] ?? 0),
                    reason: 'Dispute resolved in favor of client'
                );
            });

            return back()->with('success', __('تم حل النزاع لصالح العميل.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر حل النزاع: ') . $e->getMessage());
        }
    }

    public function resolveSplit(Request $request, Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review', 'mutual_resolution']);

        $data = $request->validate([
            'client_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'business_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'business_penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fine_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fine_on' => ['nullable', 'in:client,business'],
            'platform_fine_reason' => ['nullable', 'in:conduct,non_compliance'],
            'charge_arbitration_fee' => ['nullable', 'boolean'],
        ]);

        $clientPercent = (float) $data['client_percent'];
        $businessPercent = (float) $data['business_percent'];

        if (round($clientPercent + $businessPercent, 2) !== 100.00) {
            throw ValidationException::withMessages([
                'client_percent' => __('مجموع النسب يجب أن يساوي 100%.'),
            ]);
        }

        try {
            DB::transaction(function () use ($dispute, $clientPercent, $businessPercent, $data) {
                $resolved = $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'split',
                    resolutionPayload: [
                        'client_percent' => $clientPercent,
                        'business_percent' => $businessPercent,
                        'notes' => $data['notes'] ?? null,
                        'client_penalty_amount' => (float) ($data['client_penalty_amount'] ?? 0),
                        'business_penalty_amount' => (float) ($data['business_penalty_amount'] ?? 0),
                    ],
                    actorId: auth()->id()
                );

                $this->recordBookingDisputeResult($resolved, 'split');

                $this->applyPlatformFineIfNeeded($resolved, $data);

                $this->chargeArbitrationFeeIfSet($resolved, $data);

                $this->applyGuaranteePenaltyIfNeeded(
                    dispute: $resolved,
                    loserSide: 'client',
                    amount: (float) ($data['client_penalty_amount'] ?? 0),
                    reason: 'Dispute split penalty against client'
                );

                $this->applyGuaranteePenaltyIfNeeded(
                    dispute: $resolved,
                    loserSide: 'business',
                    amount: (float) ($data['business_penalty_amount'] ?? 0),
                    reason: 'Dispute split penalty against business'
                );
            });

            return back()->with('success', __('تم حل النزاع بنسبة توزيع بين الطرفين.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر تنفيذ split: ') . $e->getMessage());
        }
    }

    public function resolveNoAction(Dispute $dispute)
    {
        $this->ensureDisputeStatus($dispute, ['open', 'under_review', 'mutual_resolution']);

        try {
            DB::transaction(function () use ($dispute) {
                $resolved = $this->disputeService->resolve(
                    dispute: $dispute,
                    resolutionType: 'no_action',
                    resolutionPayload: [],
                    actorId: auth()->id()
                );

                $this->recordBookingDisputeResult($resolved, 'no_action');
            });

            return back()->with('success', __('تم حل النزاع بدون إجراء مالي.'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('تعذر إنهاء النزاع: ') . $e->getMessage());
        }
    }

    protected function resolveDisputeable(Dispute $dispute): mixed
    {
        if (
            isset($dispute->disputeable_type, $dispute->disputeable_id) &&
            $dispute->disputeable_type &&
            $dispute->disputeable_id &&
            class_exists($dispute->disputeable_type)
        ) {
            try {
                return $dispute->disputeable_type::find($dispute->disputeable_id);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    protected function statusOptions(): array
    {
        return [
            'open'         => 'Open',
            'under_review' => 'Under Review',
            'resolved'     => 'Resolved',
            'closed'       => 'Closed',
            'cancelled'    => 'Cancelled',
        ];
    }

    protected function ensureDisputeStatus(Dispute $dispute, array $allowed): void
    {
        if (! in_array($dispute->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('الحالة الحالية للنزاع لا تسمح بهذه العملية.'),
            ]);
        }
    }
    protected function recordBookingDisputeResult(Dispute $dispute, string $resolutionType): void
    {
        if ((string) $dispute->disputeable_type !== Booking::class) {
            return;
        }

        $booking = Booking::query()
            ->with(['user', 'business'])
            ->find((int) $dispute->disputeable_id);

        if (! $booking) {
            return;
        }

        match ($resolutionType) {
            'release_business' => $this->bookingGuaranteeIntegration->recordDisputeLostForClient($booking),
            'refund_client' => $this->bookingGuaranteeIntegration->recordDisputeLostForBusiness($booking),
            'split' => null,
            'no_action' => null,
            default => null,
        };
    }
    
    /**
     * A cash fine paid to the platform, distinct from the guarantee penalty
     * above: that one burns COVERAGE (a trust consequence), this one takes
     * BALANCE. A party can easily have one and not the other, so an arbitrator
     * has to be able to reach for either.
     */
    /**
     * The fee agreed BEFORE the case was heard, charged to the LOSING party.
     *
     * Neither the amount nor the payer is chosen here: the amount was fixed at
     * acceptance, and who lost was decided by the ruling itself. Letting an
     * arbitrator name the payer would turn the fee into a second penalty handed
     * out at will.
     */
    protected function chargeArbitrationFeeIfSet(Dispute $dispute, array $data): void
    {
        if (! filter_var($data['charge_arbitration_fee'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        app(\App\Services\ArbitrationService::class)->chargeArbitrationFee($dispute);
    }

    protected function applyPlatformFineIfNeeded(Dispute $dispute, array $data): void
    {
        $amount = round((float) ($data['platform_fine_amount'] ?? 0), 2);

        if ($amount <= 0) {
            return;
        }

        app(\App\Services\ArbitrationService::class)->applyPlatformFine(
            dispute: $dispute,
            side: (string) ($data['platform_fine_on'] ?? ''),
            amount: $amount,
            reason: (string) ($data['platform_fine_reason'] ?? '')
        );
    }

    protected function applyGuaranteePenaltyIfNeeded(
        Dispute $dispute,
        string $loserSide,
        float $amount,
        string $reason
    ): void {
        $amount = round(max($amount, 0), 2);

        if ($amount <= 0) {
            return;
        }

        if ((string) $dispute->disputeable_type !== Booking::class) {
            return;
        }

        $booking = Booking::query()
            ->with(['user', 'business'])
            ->find((int) $dispute->disputeable_id);

        if (! $booking) {
            return;
        }

        if ($loserSide === 'client' && $booking->user) {
            $this->guaranteePenaltyService->applyPenalty(
                user: $booking->user,
                amount: $amount,
                targetType: GuaranteeLevel::TARGET_CLIENT,
                referenceType: Dispute::class,
                referenceId: (int) $dispute->id,
                reason: $reason,
                meta: [
                    'booking_id' => (int) $booking->id,
                    'loser_side' => 'client',
                    'idempotency_key' => 'dispute_penalty_client_' . $dispute->id,
                ]
            );
        }

        if ($loserSide === 'business' && $booking->business) {
            $this->guaranteePenaltyService->applyPenalty(
                user: $booking->business,
                amount: $amount,
                targetType: GuaranteeLevel::TARGET_BUSINESS,
                referenceType: Dispute::class,
                referenceId: (int) $dispute->id,
                reason: $reason,
                meta: [
                    'booking_id' => (int) $booking->id,
                    'loser_side' => 'business',
                    'idempotency_key' => 'dispute_penalty_business_' . $dispute->id,
                ]
            );
        }
    }
}