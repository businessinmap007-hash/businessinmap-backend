<?php

namespace App\Support\AdminV2\Operations;

use App\Models\Booking;

final class OperationPresenter
{
    public function __construct(
        protected OperationWorkflowService $workflowService,
    ) {
    }

    public function present(object $operation): array
    {
        $result = $this->workflowService->inspect($operation);

        if ($operation instanceof Booking) {
            return $this->presentBooking($operation, $result);
        }

        return $this->presentGeneric($result);
    }

    protected function presentGeneric(OperationWorkflowResult $result): array
    {
        $context = $result->context();

        return [
            'reference' => $context->reference()->toArray(),
            'context' => $context->toArray(),

            'stage' => OperationStage::toArray($result->state()),
            'workflow' => $result->toArray(),

            'next_action' => $this->presentNextAction($result),
            'actions' => $this->presentActions($result->availableActions()),

            'blocked_reasons' => $result->blockedReasons(),
            'warnings' => $result->warnings(),

            'summary' => [
                'title' => $context->reference()->key(),
                'subtitle' => null,
                'status' => $result->label(),
                'tone' => $result->statusTone(),
            ],
        ];
    }

    protected function presentBooking(Booking $booking, OperationWorkflowResult $result): array
    {
        $context = $result->context();
        $meta = $result->meta();

        $booking->loadMissing([
            'user:id,name,code,type,phone,email',
            'business:id,name,code,type,phone,email,category_id,category_child_id',
            'service:id,key,name_ar,name_en',
            'bookable',
            'latestDeposit',
            'latestDispute',
        ]);

        return [
            'reference' => $context->reference()->toArray(),
            'context' => $context->toArray(),

            'stage' => OperationStage::toArray($result->state()),
            'workflow' => $result->toArray(),

            'summary' => [
                'title' => 'Booking #' . (int) $booking->id,
                'subtitle' => $this->bookingSubtitle($booking),
                'status' => $result->label(),
                'tone' => $result->statusTone(),
                'raw_status' => (string) $booking->status,
            ],

            'participants' => [
                'client' => [
                    'id' => (int) $booking->user_id,
                    'name' => (string) ($booking->user?->name ?? '—'),
                    'code' => (string) ($booking->user?->code ?? ''),
                    'phone' => (string) ($booking->user?->phone ?? ''),
                    'email' => (string) ($booking->user?->email ?? ''),
                ],
                'business' => [
                    'id' => (int) $booking->business_id,
                    'name' => (string) ($booking->business?->name ?? '—'),
                    'code' => (string) ($booking->business?->code ?? ''),
                    'phone' => (string) ($booking->business?->phone ?? ''),
                    'email' => (string) ($booking->business?->email ?? ''),
                    'category_id' => (int) ($booking->business?->category_id ?? 0),
                    'child_id' => (int) ($booking->business?->category_child_id ?? 0),
                ],
            ],

            'service' => [
                'id' => (int) $booking->service_id,
                'key' => (string) ($booking->service?->key ?? ''),
                'name' => $this->serviceName($booking),
                'name_ar' => (string) ($booking->service?->name_ar ?? ''),
                'name_en' => (string) ($booking->service?->name_en ?? ''),
            ],

            'schedule' => [
                'starts_at' => optional($booking->starts_at)->toDateTimeString(),
                'ends_at' => optional($booking->ends_at)->toDateTimeString(),
                'date' => optional($booking->date)->toDateString(),
                'time' => (string) ($booking->time ?? ''),
                'timezone' => (string) ($booking->timezone ?? 'Africa/Cairo'),
                'duration_value' => (int) ($booking->duration_value ?? 0),
                'duration_unit' => (string) ($booking->duration_unit ?? ''),
                'all_day' => (bool) ($booking->all_day ?? false),
            ],

            'pricing' => $this->presentPricing($booking, $context),

            'deposit' => $this->presentDeposit($booking, $meta),

            'fees' => $this->presentFees($booking, $meta),

            'confirmations' => [
                'client_confirmed' => (bool) data_get($meta, 'confirmations.client_confirmed', false),
                'business_confirmed' => (bool) data_get($meta, 'confirmations.business_confirmed', false),
                'source' => (string) data_get($meta, 'confirmations.source', ''),
            ],

            'dispute' => [
                'has_dispute' => (bool) data_get($meta, 'dispute.has_dispute', false),
                'id' => data_get($meta, 'dispute.id'),
                'status' => data_get($meta, 'dispute.status'),
            ],

            'next_action' => $this->presentNextAction($result),
            'actions' => $this->presentActions($result->availableActions()),

            'blocked_reasons' => $result->blockedReasons(),
            'warnings' => $result->warnings(),

            'timeline' => $this->presentBookingTimeline($booking, $result),

            'debug' => [
                'reference_type' => $context->referenceType(),
                'reference_id' => $context->referenceIdAsString(),
                'category_id' => $context->categoryId,
                'child_id' => $context->childId,
                'platform_service_id' => $context->platformServiceId,
                'fee_row_id' => data_get($meta, 'fees.fee_row_id'),
                'category_child_service_fee_id' => data_get($meta, 'fees.category_child_service_fee_id'),
            ],
        ];
    }

    protected function presentNextAction(OperationWorkflowResult $result): ?array
    {
        $action = $result->nextAction();

        if (! $action) {
            return null;
        }

        return OperationAction::toArray($action);
    }

    protected function presentActions(array $actions): array
    {
        return OperationAction::listFor($actions);
    }

    protected function bookingSubtitle(Booking $booking): string
    {
        $client = (string) ($booking->user?->name ?? 'Client #' . $booking->user_id);
        $business = (string) ($booking->business?->name ?? 'Business #' . $booking->business_id);
        $service = $this->serviceName($booking);

        return "{$client} / {$business} / {$service}";
    }

    protected function serviceName(Booking $booking): string
    {
        return (string) (
            $booking->service?->name_ar
            ?: $booking->service?->name_en
            ?: $booking->service?->key
            ?: ('Service #' . $booking->service_id)
        );
    }

    protected function presentPricing(Booking $booking, OperationContext $context): array
    {
        $pricing = data_get($booking->meta ?? [], 'pricing', []);

        if (! is_array($pricing)) {
            $pricing = [];
        }

        return [
            'amount' => $context->amount(),
            'currency' => $context->currency(),
            'price' => round((float) ($booking->price ?? 0), 2),

            'original_price' => round((float) data_get($pricing, 'original_price', $booking->price ?? 0), 2),
            'final_price' => round((float) data_get($pricing, 'final_price', $booking->price ?? 0), 2),
            'discount_enabled' => (bool) data_get($pricing, 'discount_enabled', false),
            'discount_percent' => (int) data_get($pricing, 'discount_percent', 0),
            'discount_amount' => round((float) data_get($pricing, 'discount_amount', 0), 2),
            'unit_price' => round((float) data_get($pricing, 'unit_price', $booking->price ?? 0), 2),
            'quantity' => (int) data_get($pricing, 'quantity', $booking->quantity ?? 1),
            'source' => (string) data_get($pricing, 'source', ''),
            'business_service_price_id' => data_get($pricing, 'business_service_price_id'),
        ];
    }

    protected function presentDeposit(Booking $booking, array $workflowMeta): array
    {
        $deposit = $booking->latestDeposit;
        $policy = data_get($workflowMeta, 'deposit', []);

        if (! is_array($policy)) {
            $policy = [];
        }

        return [
            'required' => (bool) data_get($policy, 'required', false),
            'amount' => round((float) data_get($policy, 'amount', data_get($policy, 'hold', 0)), 2),
            'hold' => round((float) data_get($policy, 'hold', data_get($policy, 'amount', 0)), 2),
            'currency' => (string) data_get($policy, 'currency', 'EGP'),

            'exists' => (bool) $deposit,
            'id' => $deposit ? (int) $deposit->id : null,
            'status' => $deposit ? (string) ($deposit->status ?? '') : null,
            'client_confirmed' => (bool) data_get($workflowMeta, 'confirmations.client_confirmed', $deposit ? (bool) ($deposit->client_confirmed ?? false) : false),
            'business_confirmed' => (bool) data_get($workflowMeta, 'confirmations.business_confirmed', $deposit ? (bool) ($deposit->business_confirmed ?? false) : false),
            'confirmation_source' => (string) data_get($workflowMeta, 'confirmations.source', ''),
            'is_frozen' => $deposit && method_exists($deposit, 'isFrozen')
                ? (bool) $deposit->isFrozen()
                : ((string) ($deposit->status ?? '') === 'frozen'),
        ];
    }

    protected function presentFees(Booking $booking, array $workflowMeta): array
    {
        $fees = data_get($workflowMeta, 'fees', []);

        if (! is_array($fees)) {
            $fees = [];
        }

        $snapshot = data_get($booking->meta ?? [], 'service_fees_snapshot', []);

        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        return [
            'charged' => (bool) data_get($fees, 'charged', false),
            'charged_at' => data_get($fees, 'charged_at'),

            'client_amount' => round((float) data_get($fees, 'client_amount', 0), 2),
            'business_amount' => round((float) data_get($fees, 'business_amount', 0), 2),

            'transactions' => data_get($fees, 'transactions', []),
            'fee_row_id' => data_get($fees, 'fee_row_id'),
            'category_child_service_fee_id' => data_get($fees, 'category_child_service_fee_id'),

            'snapshot' => $snapshot,
            'client_snapshot' => data_get($snapshot, 'client'),
            'business_snapshot' => data_get($snapshot, 'business'),
        ];
    }

    protected function presentBookingTimeline(Booking $booking, OperationWorkflowResult $result): array
    {
        $meta = $result->meta();

        return [
            [
                'key' => 'created',
                'label' => 'تم إنشاء الحجز',
                'done' => true,
                'time' => optional($booking->created_at)->toDateTimeString(),
                'tone' => 'success',
            ],
            [
                'key' => 'deposit',
                'label' => 'Deposit',
                'done' => (bool) data_get($meta, 'deposit.required', false)
                    ? (bool) data_get($result->flags(), 'deposit_frozen', false)
                    : true,
                'time' => null,
                'tone' => data_get($result->flags(), 'deposit_frozen', false) ? 'success' : 'warning',
            ],
            [
                'key' => 'client_confirmed',
                'label' => 'تأكيد العميل',
                'done' => (bool) data_get($result->flags(), 'client_confirmed', false),
                'time' => null,
                'tone' => data_get($result->flags(), 'client_confirmed', false) ? 'success' : 'warning',
            ],
            [
                'key' => 'business_confirmed',
                'label' => 'تأكيد البزنس',
                'done' => (bool) data_get($result->flags(), 'business_confirmed', false),
                'time' => null,
                'tone' => data_get($result->flags(), 'business_confirmed', false) ? 'success' : 'warning',
            ],
            [
                'key' => 'in_progress',
                'label' => 'بدء التنفيذ',
                'done' => in_array((string) $booking->status, [
                    Booking::STATUS_IN_PROGRESS,
                    Booking::STATUS_COMPLETED,
                ], true),
                'time' => data_get($booking->meta ?? [], '_execution_fee.charged_at'),
                'tone' => in_array((string) $booking->status, [
                    Booking::STATUS_IN_PROGRESS,
                    Booking::STATUS_COMPLETED,
                ], true) ? 'success' : 'muted',
            ],
            [
                'key' => 'completed',
                'label' => 'مكتمل',
                'done' => (string) $booking->status === Booking::STATUS_COMPLETED,
                'time' => null,
                'tone' => (string) $booking->status === Booking::STATUS_COMPLETED ? 'success' : 'muted',
            ],
        ];
    }
}