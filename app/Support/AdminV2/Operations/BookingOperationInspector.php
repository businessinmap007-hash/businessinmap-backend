<?php

namespace App\Support\AdminV2\Operations;

use App\Models\Booking;
use App\Models\Deposit;

final class BookingOperationInspector
{
    public function inspect(Booking $booking): OperationWorkflowResult
    {
        $booking->loadMissing([
            'business:id,name,category_id,category_child_id',
            'service:id,key,name_ar,name_en',
            'latestDeposit',
            'latestDispute',
        ]);

        $context = OperationContext::fromBooking($booking);

        $deposit = $this->latestDeposit($booking);
        $depositPolicy = $this->depositPolicy($booking);
        $confirmState = $this->confirmState($booking, $deposit);
        $feeState = $this->feeState($booking);
        $disputeState = $this->disputeState($booking);

        $stage = $this->resolveStage(
            booking: $booking,
            deposit: $deposit,
            depositPolicy: $depositPolicy,
            confirmState: $confirmState,
            feeState: $feeState,
            disputeState: $disputeState
        );

        $blockedReasons = $this->blockedReasons(
            booking: $booking,
            deposit: $deposit,
            depositPolicy: $depositPolicy,
            confirmState: $confirmState,
            feeState: $feeState,
            disputeState: $disputeState
        );

        $availableActions = $this->availableActions(
            booking: $booking,
            stage: $stage,
            deposit: $deposit,
            depositPolicy: $depositPolicy,
            confirmState: $confirmState,
            feeState: $feeState,
            disputeState: $disputeState,
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
            warnings: $this->warnings($booking, $deposit, $depositPolicy, $feeState),
            flags: [
                'ready' => $stage === OperationStage::READY_TO_START,
                'final' => OperationStage::isFinal($stage),
                'needs_action' => OperationStage::needsAction($stage),
                'deposit_required' => (bool) ($depositPolicy['required'] ?? false),
                'deposit_exists' => (bool) $deposit,
                'deposit_frozen' => $deposit ? $this->depositIsFrozen($deposit) : false,
                'client_confirmed' => (bool) ($confirmState['client_confirmed'] ?? false),
                'business_confirmed' => (bool) ($confirmState['business_confirmed'] ?? false),
                'fees_charged' => (bool) ($feeState['charged'] ?? false),
                'has_dispute' => (bool) ($disputeState['has_dispute'] ?? false),
            ],
            meta: [
                'deposit' => $depositPolicy,
                'confirmations' => $confirmState,
                'fees' => $feeState,
                'dispute' => $disputeState,
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
        array $disputeState
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
                feeState: $feeState
            ),
        };
    }

    protected function resolveActiveStage(
        Booking $booking,
        ?Deposit $deposit,
        array $depositPolicy,
        array $confirmState,
        array $feeState
    ): string {
        $depositRequired = (bool) ($depositPolicy['required'] ?? false);

        if ($depositRequired) {
            if (! $deposit) {
                return OperationStage::DEPOSIT_REQUIRED;
            }

            if (! $this->depositIsFrozen($deposit)) {
                return OperationStage::DEPOSIT_REQUIRED;
            }

            if (! $this->bothConfirmed($confirmState)) {
                return OperationStage::AWAITING_CONFIRMATION;
            }

            return OperationStage::READY_TO_START;
        }

        if (! $this->bothConfirmed($confirmState)) {
            return OperationStage::AWAITING_CONFIRMATION;
        }

        return OperationStage::READY_TO_START;
    }

    protected function blockedReasons(
        Booking $booking,
        ?Deposit $deposit,
        array $depositPolicy,
        array $confirmState,
        array $feeState,
        array $disputeState
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

        if ((bool) ($depositPolicy['required'] ?? false)) {
            if (! $deposit) {
                $reasons[] = 'الـ Deposit مطلوب ولم يتم إنشاؤه بعد.';
            } elseif (! $this->depositIsFrozen($deposit)) {
                $reasons[] = 'الـ Deposit موجود لكنه غير مجمد.';
            }
        }

        if (! (bool) ($confirmState['client_confirmed'] ?? false)) {
            $reasons[] = 'تأكيد العميل مطلوب قبل بدء التنفيذ.';
        }

        if (! (bool) ($confirmState['business_confirmed'] ?? false)) {
            $reasons[] = 'تأكيد البزنس مطلوب قبل بدء التنفيذ.';
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

        if ((bool) ($depositPolicy['required'] ?? false) && ! $deposit) {
            $actions[] = OperationAction::FREEZE_DEPOSIT;
        }

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

        if ($stage === OperationStage::READY_TO_START && empty($blockedReasons)) {
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
            OperationAction::FREEZE_DEPOSIT,
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
        array $feeState
    ): array {
        $warnings = [];

        if ((bool) ($feeState['charged'] ?? false)) {
            $warnings[] = 'تم خصم رسوم التنفيذ لهذا الحجز بالفعل.';
        }

        if ((bool) ($depositPolicy['required'] ?? false) && ! $deposit) {
            $warnings[] = 'هذا الحجز يتطلب Deposit قبل بدء التنفيذ.';
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
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $policy = data_get($meta, 'deposit_policy', []);

        return is_array($policy) ? $policy : [];
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