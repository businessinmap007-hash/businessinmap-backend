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
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent,fee_type,fee_value',
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
                    'type' => (string) ($booking->user?->type ?? ''),
                    'phone' => (string) ($booking->user?->phone ?? ''),
                    'email' => (string) ($booking->user?->email ?? ''),
                ],
                'business' => [
                    'id' => (int) $booking->business_id,
                    'name' => (string) ($booking->business?->name ?? '—'),
                    'code' => (string) ($booking->business?->code ?? ''),
                    'type' => (string) ($booking->business?->type ?? ''),
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
                'supports_deposit' => (bool) ($booking->service?->supports_deposit ?? false),
                'max_deposit_percent' => (int) ($booking->service?->max_deposit_percent ?? 0),
                'fee_type' => (string) ($booking->service?->fee_type ?? ''),
                'fee_value' => $booking->service?->fee_value !== null
                    ? (float) $booking->service->fee_value
                    : null,
            ],

            'schedule' => $this->presentSchedule($booking),

            'pricing' => $this->presentPricing($booking, $context),

            'bookable' => $this->presentBookable($booking),

            'deposit' => $this->presentDeposit($booking, $meta),

            'fees' => $this->presentFees($booking, $meta),

            'confirmations' => $this->presentConfirmations($booking, $meta),

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
                'booking_id' => (int) $booking->id,
                'user_id' => (int) $booking->user_id,
                'business_id' => (int) $booking->business_id,
                'bookable_type' => (string) ($booking->bookable_type ?? ''),
                'bookable_id' => $booking->bookable_id ? (int) $booking->bookable_id : null,
                'fee_row_id' => data_get($meta, 'fees.fee_row_id'),
                'category_child_service_fee_id' => data_get($meta, 'fees.category_child_service_fee_id'),
                'fees_charged' => (bool) data_get($meta, 'fees.charged', false),
                'charged_at' => data_get($meta, 'fees.charged_at'),
                'deposit_required' => (bool) data_get($meta, 'deposit.required', false),
                'deposit_exists' => (bool) data_get($result->flags(), 'deposit_exists', false),
                'deposit_frozen' => (bool) data_get($result->flags(), 'deposit_frozen', false),
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

    protected function presentSchedule(Booking $booking): array
    {
        return [
            'starts_at' => optional($booking->starts_at)->toDateTimeString(),
            'ends_at' => optional($booking->ends_at)->toDateTimeString(),
            'date' => optional($booking->date)->toDateString(),
            'time' => (string) ($booking->time ?? ''),
            'timezone' => (string) ($booking->timezone ?? 'Africa/Cairo'),
            'duration_value' => (int) ($booking->duration_value ?? 0),
            'duration_unit' => (string) ($booking->duration_unit ?? ''),
            'duration_label' => $this->durationLabel(
                (int) ($booking->duration_value ?? 0),
                (string) ($booking->duration_unit ?? '')
            ),
            'all_day' => (bool) ($booking->all_day ?? false),
            'quantity' => (int) ($booking->quantity ?? 1),
            'party_size' => $booking->party_size !== null ? (int) $booking->party_size : null,
        ];
    }

    protected function durationLabel(int $value, string $unit): string
    {
        if ($value <= 0) {
            return '—';
        }

        $unitLabel = match ($unit) {
            'day' => 'يوم',
            'hour' => 'ساعة',
            'minute' => 'دقيقة',
            'week' => 'أسبوع',
            'month' => 'شهر',
            'year' => 'سنة',
            default => $unit ?: 'مدة',
        };

        return $value . ' ' . $unitLabel;
    }

    protected function presentPricing(Booking $booking, OperationContext $context): array
    {
        $pricing = data_get($booking->meta ?? [], 'pricing', []);

        if (! is_array($pricing)) {
            $pricing = [];
        }

        return [
            'amount' => round((float) $context->amount(), 2),
            'currency' => (string) ($context->currency() ?: data_get($pricing, 'currency', 'EGP')),
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
            'bookable_id' => data_get($pricing, 'bookable_id', $booking->bookable_id),
            'computed_at' => data_get($pricing, 'computed_at'),
        ];
    }

    protected function presentBookable(Booking $booking): array
    {
        $bookableMeta = data_get($booking->meta ?? [], 'bookable_item', []);

        if (! is_array($bookableMeta)) {
            $bookableMeta = [];
        }

        $bookable = $booking->bookable;

        return [
            'exists' => (bool) ($bookable || ! empty($bookableMeta)),
            'id' => data_get($bookableMeta, 'id', $booking->bookable_id),
            'type' => (string) ($booking->bookable_type ?? ''),
            'title' => (string) data_get($bookableMeta, 'title', $bookable?->title ?? ''),
            'code' => (string) data_get($bookableMeta, 'code', $bookable?->code ?? ''),
            'item_type' => (string) data_get($bookableMeta, 'item_type', $bookable?->item_type ?? ''),
            'price' => round((float) data_get($bookableMeta, 'price', $bookable?->price ?? 0), 2),
            'capacity' => data_get($bookableMeta, 'capacity', $bookable?->capacity),
            'quantity' => data_get($bookableMeta, 'quantity', $bookable?->quantity),
            'deposit_enabled' => (bool) data_get($bookableMeta, 'deposit_enabled', $bookable?->deposit_enabled ?? false),
            'deposit_percent' => (int) data_get($bookableMeta, 'deposit_percent', $bookable?->deposit_percent ?? 0),
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

            'configured_percent' => (int) data_get($policy, 'configured_percent', 0),
            'source' => (string) data_get($policy, 'source', ''),
            'computed_live' => (bool) data_get($policy, 'computed_live', false),
            'ignored_old_meta' => (bool) data_get($policy, 'ignored_old_meta', false),

            'service_supports_deposit' => (bool) data_get($policy, 'service_supports_deposit', false),
            'service_max_percent' => (int) data_get($policy, 'service_max_percent', 0),

            'business_deposit_enabled' => (bool) data_get($policy, 'business_deposit_enabled', false),
            'business_deposit_percent' => (int) data_get($policy, 'business_deposit_percent', 0),

            'bookable_deposit_enabled' => (bool) data_get($policy, 'bookable_deposit_enabled', false),
            'bookable_deposit_percent' => (int) data_get($policy, 'bookable_deposit_percent', 0),

            'exists' => (bool) $deposit,
            'id' => $deposit ? (int) $deposit->id : null,
            'status' => $deposit ? (string) ($deposit->status ?? '') : null,
            'total_amount' => $deposit ? round((float) ($deposit->total_amount ?? 0), 2) : 0.00,
            'client_amount' => $deposit ? round((float) ($deposit->client_amount ?? 0), 2) : 0.00,
            'business_amount' => $deposit ? round((float) ($deposit->business_amount ?? 0), 2) : 0.00,

            'client_confirmed' => (bool) data_get(
                $workflowMeta,
                'confirmations.client_confirmed',
                $deposit ? (bool) ($deposit->client_confirmed ?? false) : false
            ),
            'business_confirmed' => (bool) data_get(
                $workflowMeta,
                'confirmations.business_confirmed',
                $deposit ? (bool) ($deposit->business_confirmed ?? false) : false
            ),
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

        $transactions = data_get($fees, 'transactions', []);

        if (! is_array($transactions)) {
            $transactions = [];
        }

        $clientAmount = round((float) data_get($fees, 'client_amount', 0), 2);
        $businessAmount = round((float) data_get($fees, 'business_amount', 0), 2);

        return [
            'charged' => (bool) data_get($fees, 'charged', false),
            'charged_at' => data_get($fees, 'charged_at'),

            'client_amount' => $clientAmount,
            'business_amount' => $businessAmount,
            'total_amount' => round($clientAmount + $businessAmount, 2),

            'transactions' => $transactions,
            'transactions_count' => count($transactions),

            'fee_row_id' => data_get($fees, 'fee_row_id'),
            'category_child_service_fee_id' => data_get($fees, 'category_child_service_fee_id'),

            'snapshot' => $snapshot,
            'client_snapshot' => data_get($snapshot, 'client'),
            'business_snapshot' => data_get($snapshot, 'business'),
        ];
    }

    protected function presentConfirmations(Booking $booking, array $workflowMeta): array
    {
        $confirmations = data_get($workflowMeta, 'confirmations', []);

        if (! is_array($confirmations)) {
            $confirmations = [];
        }

        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $startConfirm = is_array(data_get($meta, '_start_confirm'))
            ? data_get($meta, '_start_confirm')
            : [];

        return [
            'client_confirmed' => (bool) data_get($confirmations, 'client_confirmed', false),
            'business_confirmed' => (bool) data_get($confirmations, 'business_confirmed', false),
            'source' => (string) data_get($confirmations, 'source', ''),

            'client_at' => data_get($startConfirm, 'client_at'),
            'business_at' => data_get($startConfirm, 'business_at'),

            'deposit_client_confirmed' => (bool) data_get($confirmations, 'deposit_client_confirmed', false),
            'deposit_business_confirmed' => (bool) data_get($confirmations, 'deposit_business_confirmed', false),
            'meta_client_confirmed' => (bool) data_get($confirmations, 'meta_client_confirmed', false),
            'meta_business_confirmed' => (bool) data_get($confirmations, 'meta_business_confirmed', false),
        ];
    }

    protected function presentBookingTimeline(Booking $booking, OperationWorkflowResult $result): array
    {
        $meta = $result->meta();
        $flags = $result->flags();

        $bookingMeta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $startConfirm = is_array(data_get($bookingMeta, '_start_confirm'))
            ? data_get($bookingMeta, '_start_confirm')
            : [];

        $depositRequired = (bool) data_get($meta, 'deposit.required', false);
        $depositFrozen = (bool) data_get($flags, 'deposit_frozen', false);
        $clientConfirmed = (bool) data_get($flags, 'client_confirmed', false);
        $businessConfirmed = (bool) data_get($flags, 'business_confirmed', false);

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
                'label' => $depositRequired ? 'Deposit مطلوب' : 'لا يوجد Deposit',
                'done' => $depositRequired ? $depositFrozen : true,
                'time' => optional($booking->latestDeposit?->updated_at)->toDateTimeString(),
                'tone' => $depositRequired
                    ? ($depositFrozen ? 'success' : 'warning')
                    : 'success',
            ],
            [
                'key' => 'client_confirmed',
                'label' => 'تأكيد العميل',
                'done' => $clientConfirmed,
                'time' => data_get($startConfirm, 'client_at'),
                'tone' => $clientConfirmed ? 'success' : 'warning',
            ],
            [
                'key' => 'business_confirmed',
                'label' => 'تأكيد البزنس',
                'done' => $businessConfirmed,
                'time' => data_get($startConfirm, 'business_at'),
                'tone' => $businessConfirmed ? 'success' : 'warning',
            ],
            [
                'key' => 'in_progress',
                'label' => 'بدء التنفيذ',
                'done' => in_array((string) $booking->status, [
                    Booking::STATUS_IN_PROGRESS,
                    Booking::STATUS_COMPLETED,
                ], true),
                'time' => data_get($bookingMeta, '_execution_fee.charged_at'),
                'tone' => in_array((string) $booking->status, [
                    Booking::STATUS_IN_PROGRESS,
                    Booking::STATUS_COMPLETED,
                ], true) ? 'success' : 'muted',
            ],
            [
                'key' => 'completed',
                'label' => 'مكتمل',
                'done' => (string) $booking->status === Booking::STATUS_COMPLETED,
                'time' => (string) $booking->updated_at,
                'tone' => (string) $booking->status === Booking::STATUS_COMPLETED ? 'success' : 'muted',
            ],
        ];
    }
}