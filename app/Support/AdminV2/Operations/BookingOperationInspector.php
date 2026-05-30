<?php

namespace App\Support\AdminV2\Operations;

use App\Models\Booking;
use App\Models\Deposit;
use App\Services\ServiceExecutionEngine;
use Throwable;

final class BookingOperationInspector
{
    public function __construct(
        protected ServiceExecutionEngine $serviceExecutionEngine
    ) {
    }

    public function inspect(Booking $booking): OperationWorkflowResult
    {
        $booking->loadMissing([
            'business:id,name,category_id,category_child_id',
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
            'bookable',
            'latestDeposit',
            'latestDispute',
        ]);

        $context = OperationContext::fromBooking($booking);

        $deposit = $this->latestDeposit($booking);
        $depositPolicy = $this->depositPolicy($booking);
        $confirmState = $this->confirmState($booking, $deposit);
        $feeState = $this->feeState($booking);
        $disputeState = $this->disputeState($booking);
        $financialState = $this->financialState($booking);

        $stage = $this->resolveStage(
            booking: $booking,
            deposit: $deposit,
            depositPolicy: $depositPolicy,
            confirmState: $confirmState,
            feeState: $feeState,
            disputeState: $disputeState,
            financialState: $financialState
        );

        $blockedReasons = $this->blockedReasons(
            booking: $booking,
            deposit: $deposit,
            depositPolicy: $depositPolicy,
            confirmState: $confirmState,
            feeState: $feeState,
            disputeState: $disputeState,
            financialState: $financialState
        );

        $availableActions = $this->availableActions(
            booking: $booking,
            stage: $stage,
            deposit: $deposit,
            depositPolicy: $depositPolicy,
            confirmState: $confirmState,
            feeState: $feeState,
            disputeState: $disputeState,
            financialState: $financialState,
            blockedReasons: $blockedReasons
        );

        $nextAction = $this->nextAction($availableActions, $blockedReasons, $stage);

        return OperationWorkflowResult::make(
            context: $context,
            state: $stage,
            label: OperationStage::label($stage),
            nextAction: $nextAction,
            availableActions: $availableActions,
            blockedReasons: $blockedReasons,
            warnings: $this->warnings($booking, $deposit, $depositPolicy, $feeState, $financialState),
            flags: [
                'ready' => $stage === OperationStage::READY_TO_START && empty($blockedReasons),
                'final' => OperationStage::isFinal($stage),
                'needs_action' => OperationStage::needsAction($stage),

                'deposit_required' => (bool) ($depositPolicy['required'] ?? false),
                'deposit_exists' => (bool) $deposit,
                'deposit_frozen' => $deposit ? $this->depositIsFrozen($deposit) : false,
                'deposit_auto_freeze_on_start' => (bool) ($depositPolicy['required'] ?? false)
                    && ! ($deposit && $this->depositIsFrozen($deposit)),

                'client_confirmed' => (bool) ($confirmState['client_confirmed'] ?? false),
                'business_confirmed' => (bool) ($confirmState['business_confirmed'] ?? false),

                'financial_ready' => (bool) ($financialState['ok'] ?? true),
                'fees_charged' => (bool) ($feeState['charged'] ?? false),
                'has_dispute' => (bool) ($disputeState['has_dispute'] ?? false),
            ],
            meta: [
                'deposit' => $depositPolicy,
                'confirmations' => $confirmState,
                'fees' => $feeState,
                'dispute' => $disputeState,
                'financial' => $financialState,
                'raw_status' => (string) $booking->status,
            ],
        );
    }

    protected function resolveStage(
        Booking $booking,
        ?Deposit $deposit,
        array $depositPolicy,
        array $confirmState,
        array $feeState,
        array $disputeState,
        array $financialState
    ): string {
        if ((bool) ($disputeState['has_dispute'] ?? false)) {
            return OperationStage::DISPUTED;
        }

        return match ((string) $booking->status) {
            Booking::STATUS_COMPLETED => OperationStage::COMPLETED,
            Booking::STATUS_CANCELLED => OperationStage::CANCELLED,
            Booking::STATUS_REJECTED => OperationStage::REJECTED,
            Booking::STATUS_IN_PROGRESS => OperationStage::IN_PROGRESS,
            default => $this->resolveActiveStage(
                booking: $booking,
                deposit: $deposit,
                depositPolicy: $depositPolicy,
                confirmState: $confirmState,
                feeState: $feeState,
                financialState: $financialState
            ),
        };
    }

    protected function resolveActiveStage(
        Booking $booking,
        ?Deposit $deposit,
        array $depositPolicy,
        array $confirmState,
        array $feeState,
        array $financialState
    ): string {
        if (! $this->bothConfirmed($confirmState)) {
            return OperationStage::AWAITING_CONFIRMATION;
        }

        /*
         * مهم:
         * لا نرجع DEPOSIT_REQUIRED لمجرد أن الديبوزت غير مجمد.
         * لأن ServiceExecutionEngine يقوم بتجميد الـ Deposit تلقائيًا عند Start.
         */
        return OperationStage::READY_TO_START;
    }

    protected function blockedReasons(
        Booking $booking,
        ?Deposit $deposit,
        array $depositPolicy,
        array $confirmState,
        array $feeState,
        array $disputeState,
        array $financialState
    ): array {
        $reasons = [];

        if ((bool) ($disputeState['has_dispute'] ?? false)) {
            $reasons[] = 'يوجد نزاع مفتوح على هذا الحجز.';
        }

        if (in_array((string) $booking->status, [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_REJECTED,
        ], true)) {
            $reasons[] = 'الحجز في حالة نهائية ولا يمكن بدء التنفيذ.';
        }

        if (! (bool) ($confirmState['client_confirmed'] ?? false)) {
            $reasons[] = 'تأكيد العميل مطلوب قبل بدء التنفيذ.';
        }

        if (! (bool) ($confirmState['business_confirmed'] ?? false)) {
            $reasons[] = 'تأكيد البزنس مطلوب قبل بدء التنفيذ.';
        }

        /*
         * Financial Guard:
         * لا نمنع بسبب عدم وجود Deposit مجمد؛ لأنه سيتجمد تلقائيًا عند Start.
         * نمنع فقط إذا الرصيد غير كافٍ أو financialPreview فشل.
         */
        if ($this->bothConfirmed($confirmState) && ! (bool) ($financialState['ok'] ?? true)) {
            foreach (($financialState['messages'] ?? []) as $message) {
                $reasons[] = (string) $message;
            }

            if (! empty($financialState['error'])) {
                $reasons[] = 'تعذر فحص الجاهزية المالية: ' . (string) $financialState['error'];
            }
        }

        return array_values(array_unique(array_filter($reasons)));
    }

    protected function availableActions(
        Booking $booking,
        string $stage,
        ?Deposit $deposit,
        array $depositPolicy,
        array $confirmState,
        array $feeState,
        array $disputeState,
        array $financialState,
        array $blockedReasons
    ): array {
        $actions = [
            OperationAction::VIEW,
            OperationAction::EDIT,
        ];

        if (OperationStage::isFinal($stage)) {
            return $actions;
        }

        if ((bool) ($disputeState['has_dispute'] ?? false)) {
            $actions[] = OperationAction::OPEN_DISPUTE;

            return array_values(array_unique($actions));
        }

        /*
         * لا نعرض Freeze Deposit كخطوة مطلوبة.
         * الـ Deposit يتم تجميده تلقائيًا داخل ServiceExecutionEngine عند Start.
         */
        if ($deposit && $this->depositIsFrozen($deposit)) {
            $actions[] = OperationAction::RELEASE_DEPOSIT;
            $actions[] = OperationAction::REFUND_DEPOSIT;
            $actions[] = OperationAction::OPEN_DISPUTE;
        }

        if (! (bool) ($confirmState['client_confirmed'] ?? false)) {
            $actions[] = OperationAction::CONFIRM_CLIENT;
        }

        if (! (bool) ($confirmState['business_confirmed'] ?? false)) {
            $actions[] = OperationAction::CONFIRM_BUSINESS;
        }

        if (
            $stage === OperationStage::READY_TO_START
            && empty($blockedReasons)
            && (bool) ($financialState['ok'] ?? true)
        ) {
            $actions[] = OperationAction::START;
        }

        if ((string) $booking->status === Booking::STATUS_IN_PROGRESS) {
            $actions[] = OperationAction::COMPLETE;
            $actions[] = OperationAction::OPEN_DISPUTE;
        }

        if (in_array((string) $booking->status, [
            Booking::STATUS_PENDING,
            Booking::STATUS_ACCEPTED,
        ], true)) {
            $actions[] = OperationAction::CANCEL;
        }

        return array_values(array_unique($actions));
    }

    protected function nextAction(array $availableActions, array $blockedReasons, string $stage): ?string
    {
        foreach ([
            OperationAction::CONFIRM_CLIENT,
            OperationAction::CONFIRM_BUSINESS,
            OperationAction::START,
            OperationAction::COMPLETE,
        ] as $action) {
            if (in_array($action, $availableActions, true)) {
                return $action;
            }
        }

        if (! empty($blockedReasons)) {
            return null;
        }

        if ($stage === OperationStage::READY_TO_START) {
            return OperationAction::START;
        }

        return null;
    }

    protected function warnings(
        Booking $booking,
        ?Deposit $deposit,
        array $depositPolicy,
        array $feeState,
        array $financialState
    ): array {
        $warnings = [];

        if ((bool) ($feeState['charged'] ?? false)) {
            $warnings[] = 'تم خصم رسوم التنفيذ لهذا الحجز بالفعل.';
        }

        if ((bool) ($depositPolicy['required'] ?? false) && ! ($deposit && $this->depositIsFrozen($deposit))) {
            $warnings[] = 'سيتم تجميد الـ Deposit تلقائيًا عند بدء التنفيذ.';
        }

        if (! empty($financialState['deposit']['required']) && ! empty($financialState['deposit']['client_required'])) {
            $warnings[] = 'بدء التنفيذ سيتطلب رصيدًا كافيًا لتجميد Deposit على الطرفين.';
        }

        if (! empty($financialState['fees']['non_refundable_after_in_progress'])) {
            $warnings[] = 'رسوم استخدام الخدمة لا تسترد بعد دخول الحجز في حيز التنفيذ.';
        }

        return array_values(array_unique(array_filter($warnings)));
    }

    protected function latestDeposit(Booking $booking): ?Deposit
    {
        if ($booking->relationLoaded('latestDeposit')) {
            return $booking->latestDeposit;
        }

        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();
    }

    protected function depositPolicy(Booking $booking): array
    {
        try {
            return $this->serviceExecutionEngine->depositPolicy($booking);
        } catch (Throwable $e) {
            report($e);

            return [
                'required' => false,
                'amount' => 0.00,
                'hold' => 0.00,
                'configured_percent' => 0,
                'source' => 'deposit_policy_error',
                'currency' => 'EGP',
                'computed_live' => true,
                'ignored_old_meta' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function financialState(Booking $booking): array
    {
        try {
            $preview = $this->serviceExecutionEngine->financialPreview($booking);

            if (! is_array($preview)) {
                return [
                    'ok' => false,
                    'messages' => ['تعذر قراءة نتيجة الجاهزية المالية.'],
                    'error' => 'invalid_financial_preview',
                ];
            }

            return array_merge([
                'ok' => true,
                'messages' => [],
                'error' => null,
            ], $preview);
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'messages' => ['تعذر فحص الجاهزية المالية قبل بدء التنفيذ.'],
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function confirmState(Booking $booking, ?Deposit $deposit): array
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $confirm = is_array(data_get($meta, '_start_confirm', []))
            ? data_get($meta, '_start_confirm', [])
            : [];

        $metaClientConfirmed = ! empty($confirm['client']);
        $metaBusinessConfirmed = ! empty($confirm['business']);

        if ($deposit) {
            return [
                'client_confirmed' => ((bool) ($deposit->client_confirmed ?? false)) || $metaClientConfirmed,
                'business_confirmed' => ((bool) ($deposit->business_confirmed ?? false)) || $metaBusinessConfirmed,
                'source' => 'deposit_or_booking_meta',
                'deposit_client_confirmed' => (bool) ($deposit->client_confirmed ?? false),
                'deposit_business_confirmed' => (bool) ($deposit->business_confirmed ?? false),
                'meta_client_confirmed' => $metaClientConfirmed,
                'meta_business_confirmed' => $metaBusinessConfirmed,
            ];
        }

        return [
            'client_confirmed' => $metaClientConfirmed,
            'business_confirmed' => $metaBusinessConfirmed,
            'source' => 'booking_meta',
            'deposit_client_confirmed' => false,
            'deposit_business_confirmed' => false,
            'meta_client_confirmed' => $metaClientConfirmed,
            'meta_business_confirmed' => $metaBusinessConfirmed,
        ];
    }

    protected function feeState(Booking $booking): array
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $fee = data_get($meta, '_execution_fee', []);

        if (! is_array($fee)) {
            $fee = [];
        }

        return [
            'charged' => ! empty($fee['charged_at']),
            'charged_at' => $fee['charged_at'] ?? null,
            'client_amount' => round((float) ($fee['client_amount'] ?? 0), 2),
            'business_amount' => round((float) ($fee['business_amount'] ?? 0), 2),
            'transactions' => is_array($fee['transactions'] ?? null) ? $fee['transactions'] : [],
            'fee_row_id' => $fee['fee_row_id'] ?? null,
            'category_child_service_fee_id' => $fee['category_child_service_fee_id'] ?? null,
        ];
    }

    protected function disputeState(Booking $booking): array
    {
        $dispute = $booking->relationLoaded('latestDispute')
            ? $booking->latestDispute
            : null;

        $hasDispute = $dispute && in_array((string) ($dispute->status ?? ''), [
            'open',
            'under_review',
        ], true);

        return [
            'has_dispute' => (bool) $hasDispute,
            'id' => $hasDispute ? (int) $dispute->id : null,
            'status' => $hasDispute ? (string) $dispute->status : null,
        ];
    }

    protected function depositIsFrozen(Deposit $deposit): bool
    {
        if (method_exists($deposit, 'isFrozen')) {
            return (bool) $deposit->isFrozen();
        }

        return (string) ($deposit->status ?? '') === 'frozen';
    }

    protected function bothConfirmed(array $confirmState): bool
    {
        return (bool) ($confirmState['client_confirmed'] ?? false)
            && (bool) ($confirmState['business_confirmed'] ?? false);
    }
}