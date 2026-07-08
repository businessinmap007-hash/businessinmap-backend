<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\DepositEvent;
use App\Models\Dispute;
use App\Models\OperationGuarantor;
use App\Services\Guarantees\OperationGuarantorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingDepositService
{
    public function __construct(
        protected DepositsEscrowService $depositsEscrowService,
        protected DisputeService $disputeService,
        protected OperationGuarantorService $operationGuarantors,
    ) {
    }

    /**
     * Return any frozen guarantee coverage (client's own + friend co-guarantors)
     * for a booking. Safe to call even when there is no guarantee involved.
     * Used on completion and refund so coverage is never left frozen.
     */
    public function releaseGuarantees(Booking $booking): void
    {
        $this->operationGuarantors->releaseOperation(OperationGuarantor::OP_BOOKING, (int) $booking->id);
    }

    public function latestDeposit(?Booking $booking): ?Deposit
    {
        if (! $booking) {
            return null;
        }

        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', (int) $booking->id)
            ->orderByDesc('id')
            ->first();
    }

    public function latestDepositOrFail(Booking $booking): Deposit
    {
        $deposit = $this->latestDeposit($booking);

        if (! $deposit) {
            throw ValidationException::withMessages([
                'deposit' => 'لا يوجد Deposit لهذا الحجز.',
            ]);
        }

        return $deposit;
    }

    /**
     * Backward compatible signature:
     * - old callers pass only $holdAmount and get legacy 50/50 behavior.
     * - new engine passes $policy to create client hold + business counter hold + external snapshot.
     */
    public function freezeForBooking(Booking $booking, float $holdAmount, ?array $policy = null): Deposit
    {
        if ($holdAmount <= 0 && ! ($policy['external_deposit_required'] ?? false)) {
            throw ValidationException::withMessages([
                'deposit' => 'قيمة الـ Deposit غير صالحة.',
            ]);
        }

        $existing = $this->latestDeposit($booking);

        if ($existing && ! $existing->isFinal()) {
            $this->syncDepositConfirmationsFromBookingMeta($booking, $existing);

            if (method_exists($existing, 'isFrozen') && $existing->isFrozen()) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'deposit' => 'يوجد Deposit غير نهائي لكنه غير مجمد. لا يمكن بدء التنفيذ قبل معالجة حالة الـ Deposit.',
            ]);
        }

        $policy = $policy ?: $this->legacyPolicy($holdAmount);

        $clientHoldAmount = round((float) ($policy['wallet_hold_amount'] ?? $holdAmount), 2);
        $businessHoldAmount = round((float) ($policy['business_counter_hold_amount'] ?? $holdAmount), 2);
        $total = round($clientHoldAmount + $businessHoldAmount, 2);

        if ($total <= 0 && ! ($policy['external_deposit_required'] ?? false)) {
            throw ValidationException::withMessages([
                'deposit' => 'لا توجد قيمة Wallet Hold أو External Deposit مطلوبة.',
            ]);
        }

        $clientPercent = $total > 0 ? (int) round(($clientHoldAmount / $total) * 100) : 0;
        $businessPercent = $total > 0 ? (int) round(($businessHoldAmount / $total) * 100) : 0;

        $deposit = $this->depositsEscrowService->create(
            clientId: (int) $booking->user_id,
            businessId: (int) $booking->business_id,
            totalAmount: max($total, 0.01),
            clientPercent: $clientPercent,
            businessPercent: $businessPercent,
            targetType: Booking::class,
            targetId: (int) $booking->id,
            clientAmount: $clientHoldAmount,
            businessAmount: $businessHoldAmount,
        );

        $this->syncDepositConfirmationsFromBookingMeta($booking, $deposit);
        $this->syncNewDepositColumns($deposit, $booking, $policy, $clientHoldAmount, $businessHoldAmount);
        $this->event($deposit, 'wallet_hold_created', (float) ($policy['amount'] ?? $holdAmount), [
            'policy' => $policy,
            'client_hold_amount' => $clientHoldAmount,
            'business_counter_hold_amount' => $businessHoldAmount,
        ]);

        return $deposit->refresh();
    }

    public function releaseForBooking(Booking $booking): Deposit
    {
        $deposit = $this->latestDepositOrFail($booking);
        $deposit = $this->depositsEscrowService->release($deposit);

        $this->markHoldStatuses($deposit, 'released');
        $this->event($deposit, 'wallet_hold_released');

        // Completion: also return any frozen guarantee coverage.
        $this->releaseGuarantees($booking);

        return $deposit;
    }

    public function refundForBooking(
        Booking $booking,
        bool $refundClient = true,
        bool $refundBusiness = true
    ): Deposit {
        $deposit = $this->latestDepositOrFail($booking);
        $deposit = $this->depositsEscrowService->refund(
            $deposit,
            $refundClient,
            $refundBusiness
        );

        $this->markHoldStatuses($deposit, 'refunded');
        $this->event($deposit, 'wallet_hold_refunded');

        // Return any frozen guarantee coverage alongside the wallet refund.
        $this->releaseGuarantees($booking);

        return $deposit;
    }

    public function submitExternalDeposit(
        Booking $booking,
        float $amount,
        ?string $reference = null,
        ?string $proofPath = null,
        ?string $notes = null
    ): Deposit {
        $deposit = $this->latestDepositOrFail($booking);

        $deposit->external_deposit_required = true;
        $deposit->external_deposit_amount = round($amount, 2);
        $deposit->external_deposit_status = 'submitted';
        $deposit->external_reference = $reference;
        $deposit->external_proof_path = $proofPath;
        $deposit->external_notes = $notes;
        $deposit->external_paid_at = now();
        $deposit->save();

        $this->event($deposit, 'external_submitted', $amount, compact('reference', 'proofPath', 'notes'));

        return $deposit;
    }

    public function verifyExternalDeposit(Booking $booking, ?int $verifiedBy = null): Deposit
    {
        $deposit = $this->latestDepositOrFail($booking);

        if (! in_array((string) $deposit->external_deposit_status, ['submitted', 'pending'], true)) {
            throw ValidationException::withMessages([
                'external_deposit' => 'حالة العربون الخارجي لا تسمح بالاعتماد.',
            ]);
        }

        $bookingTotal = round((float) ($deposit->remaining_amount_before_external ?: $booking->price), 2);
        $externalAmount = round((float) $deposit->external_deposit_amount, 2);

        $deposit->external_deposit_status = 'verified';
        $deposit->external_verified_at = now();
        $deposit->external_verified_by = $verifiedBy;
        $deposit->affects_remaining_amount = true;
        $deposit->remaining_amount_before_external = $bookingTotal;
        $deposit->remaining_amount_after_external = max(round($bookingTotal - $externalAmount, 2), 0);
        $deposit->save();

        $this->event($deposit, 'external_verified', $externalAmount, [
            'verified_by' => $verifiedBy,
            'remaining_after_external' => (float) $deposit->remaining_amount_after_external,
        ]);

        return $deposit;
    }

    public function openDisputeForBooking(
        Booking $booking,
        int $openedByUserId,
        ?int $actorId = null,
        array $payload = []
    ): Dispute {
        $deposit = $this->latestDepositOrFail($booking);

        if ($deposit->isFinal()) {
            throw ValidationException::withMessages([
                'deposit' => 'لا يمكن فتح نزاع بعد إنهاء حالة الـ Deposit.',
            ]);
        }

        $existing = Dispute::query()
            ->where('disputeable_type', Booking::class)
            ->where('disputeable_id', (int) $booking->id)
            ->whereIn('status', ['open', 'mutual_resolution', 'under_review'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $dispute = $this->disputeService->openForBooking(
            booking: $booking,
            openedByUserId: $openedByUserId,
            actorId: $actorId,
            payload: array_merge($payload, [
                'deposit_id' => (int) $deposit->id,
                'type' => 'deposit',
                'policy_snapshot' => is_array($deposit->policy_snapshot ?? null) ? $deposit->policy_snapshot : [],
            ])
        );

        $this->event($deposit, 'dispute_opened', null, ['dispute_id' => (int) $dispute->id]);

        return $dispute;
    }

    protected function syncNewDepositColumns(Deposit $deposit, Booking $booking, array $policy, float $clientHoldAmount, float $businessHoldAmount): void
    {
        $updates = [
            'booking_id' => (int) $booking->id,
            'bookable_item_id' => $booking->bookable_id ? (int) $booking->bookable_id : null,
            'platform_service_id' => (int) $booking->service_id,
            'category_id' => (int) data_get($booking->meta, 'business_context.category_id', $booking->business?->category_id ?? 0),
            'category_child_id' => (int) data_get($booking->meta, 'business_context.category_child_id', $booking->business?->category_child_id ?? 0),
            'mode' => (string) ($policy['mode'] ?? $policy['deposit_mode'] ?? 'wallet_hold'),
            'calculation_base' => (string) ($policy['calculation_base'] ?? 'first_day'),
            'deposit_type' => (string) ($policy['deposit_type'] ?? 'percent'),
            'deposit_value' => (float) ($policy['deposit_value'] ?? 0),
            'deposit_percent_used' => (float) ($policy['configured_percent'] ?? 0),
            'deposit_base_amount' => (float) ($policy['base_amount'] ?? 0),
            'deposit_amount' => (float) ($policy['amount'] ?? 0),
            'wallet_hold_required' => (bool) ($policy['wallet_hold_required'] ?? false),
            'wallet_hold_amount' => $clientHoldAmount,
            'wallet_hold_status' => $clientHoldAmount > 0 ? 'held' : 'not_required',
            'business_counter_hold_required' => (bool) ($policy['business_counter_hold_required'] ?? false),
            'business_counter_hold_percent' => (float) ($policy['business_counter_hold_percent'] ?? 0),
            'business_counter_hold_amount' => $businessHoldAmount,
            'business_counter_hold_status' => $businessHoldAmount > 0 ? 'held' : 'not_required',
            'external_deposit_required' => (bool) ($policy['external_deposit_required'] ?? false),
            'external_deposit_amount' => (float) ($policy['external_deposit_amount'] ?? 0),
            'external_deposit_status' => (bool) ($policy['external_deposit_required'] ?? false) ? 'pending' : 'not_required',
            'affects_remaining_amount' => false,
            'remaining_amount_before_external' => (float) ($policy['total_amount'] ?? $booking->price ?? 0),
            'remaining_amount_after_external' => (float) ($policy['total_amount'] ?? $booking->price ?? 0),
            'policy_snapshot' => $policy,
        ];

        foreach ($updates as $key => $value) {
            if (Schema::hasColumn('deposits', $key)) {
                $deposit->{$key} = $value;
            }
        }

        $deposit->save();
    }

    protected function markHoldStatuses(Deposit $deposit, string $status): void
    {
        foreach (['wallet_hold_status', 'business_counter_hold_status'] as $column) {
            if (Schema::hasColumn('deposits', $column) && (string) $deposit->{$column} !== 'not_required') {
                $deposit->{$column} = $status;
            }
        }

        $deposit->save();
    }

    protected function event(Deposit $deposit, string $type, ?float $amount = null, array $meta = []): void
    {
        if (! Schema::hasTable('deposit_events')) {
            return;
        }

        DepositEvent::create([
            'deposit_id' => (int) $deposit->id,
            'booking_id' => (int) ($deposit->booking_id ?? $deposit->target_id),
            'dispute_id' => $meta['dispute_id'] ?? null,
            'actor_id' => auth()->id(),
            'actor_type' => auth()->id() ? 'admin' : 'system',
            'event_type' => $type,
            'amount' => $amount,
            'meta' => $meta,
        ]);
    }

    protected function syncDepositConfirmationsFromBookingMeta(Booking $booking, Deposit $deposit): void
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $confirm = is_array($meta['_start_confirm'] ?? null) ? $meta['_start_confirm'] : [];

        $dirty = false;

        if (! empty($confirm['client']) && ! (bool) $deposit->client_confirmed) {
            $deposit->client_confirmed = true;
            $dirty = true;
        }

        if (! empty($confirm['business']) && ! (bool) $deposit->business_confirmed) {
            $deposit->business_confirmed = true;
            $dirty = true;
        }

        if ($dirty) {
            $deposit->save();
        }
    }

    protected function legacyPolicy(float $holdAmount): array
    {
        return [
            'mode' => 'wallet_hold',
            'calculation_base' => 'total',
            'deposit_type' => 'fixed',
            'deposit_value' => $holdAmount,
            'configured_percent' => 0,
            'base_amount' => $holdAmount,
            'amount' => $holdAmount,
            'wallet_hold_required' => true,
            'wallet_hold_amount' => $holdAmount,
            'business_counter_hold_required' => true,
            'business_counter_hold_percent' => 50,
            'business_counter_hold_amount' => $holdAmount,
            'external_deposit_required' => false,
            'external_deposit_amount' => 0,
            'total_amount' => $holdAmount,
            'source' => 'legacy_freeze_amount',
        ];
    }
}
