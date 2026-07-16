<?php

namespace App\Services;

use App\DTO\FeeContext;
use App\Models\Address;
use App\Models\Booking;
use App\Models\ServiceFeeRule;
use App\Models\Subscription;
use App\Models\UserOperationRating;
use Illuminate\Support\Collection;

/**
 * BIM-3.5 — the dynamic fee rules engine.
 *
 * The base fee (category_child_service_fees) says what a service costs in
 * general; this says what it costs for *this* operation — its value, place,
 * hour, the payer's track record, and their subscription. See the
 * create_service_fee_rules migration for where it sits in the layering.
 *
 * Matching rules compound in priority order (a peak surcharge and a loyalty
 * discount can both land) unless a rule sets stop_on_match. Every applied rule
 * is traced, so a fee can always be explained back to the rules that made it.
 */
class ServiceFeeRuleEngine
{
    /**
     * Run the rules over a base fee.
     *
     * @return array{amount: float, base_amount: float, applied: array<int, array<string, mixed>>}
     */
    public function resolve(float $baseFee, FeeContext $context): array
    {
        $amount = round(max($baseFee, 0.0), 2);

        $result = [
            'amount' => $amount,
            'base_amount' => $amount,
            'applied' => [],
        ];

        if ($amount <= 0 && $baseFee <= 0) {
            // Nothing to adjust — but an override/waive rule on a zero fee is
            // still meaningful, so only skip when there are no rules at all.
            $result['amount'] = 0.0;
        }

        foreach ($this->rulesFor($context) as $rule) {
            if (! $rule->matches($context)) {
                continue;
            }

            $before = $result['amount'];
            $after = $rule->applyTo($before, $context);

            $result['amount'] = $after;
            $result['applied'][] = [
                'id' => (int) $rule->id,
                'name' => (string) $rule->name,
                'priority' => (int) $rule->priority,
                'effect' => (string) $rule->effect,
                'effect_value' => $rule->effect_value !== null ? (float) $rule->effect_value : null,
                'amount_before' => $before,
                'amount_after' => $after,
            ];

            if ($rule->stop_on_match) {
                break;
            }
        }

        return $result;
    }

    /**
     * Candidate rules, narrowed in SQL by scope/window and ordered for apply.
     *
     * @return Collection<int, ServiceFeeRule>
     */
    public function rulesFor(FeeContext $context): Collection
    {
        return ServiceFeeRule::query()
            ->active()
            ->runningAt($context->occurredAt ?? now())
            ->forContext($context)
            ->orderedForApply()
            ->get();
    }

    /**
     * Gather the facts for one payer on one booking. Does the lookups up front
     * so rule matching stays query-free; called once per payer per booking, not
     * per rule.
     */
    public function contextForBooking(
        Booking $booking,
        string $payer,
        float $baseAmount,
        string $feeCode,
        ?int $categoryId = null,
        ?int $childId = null
    ): FeeContext {
        $booking->loadMissing(['business:id,category_id,category_child_id', 'service:id,key']);

        $businessId = (int) $booking->business_id;
        $clientId = (int) $booking->user_id;
        $payerId = $payer === ServiceFeeRule::PAYER_BUSINESS ? $businessId : $clientId;

        $location = $this->primaryLocationFor($businessId);
        $record = $this->operationRecordFor($payerId, $payer);

        return new FeeContext(
            payer: $payer,
            feeCode: $feeCode,
            baseAmount: $baseAmount,
            serviceId: $booking->service_id ? (int) $booking->service_id : null,
            serviceKey: $booking->service?->key,
            categoryId: $categoryId ?: ($booking->business?->category_id ? (int) $booking->business->category_id : null),
            childId: $childId ?: ($booking->business?->category_child_id ? (int) $booking->business->category_child_id : null),
            businessId: $businessId ?: null,
            clientId: $clientId ?: null,
            governorateId: $location['governorate_id'],
            cityId: $location['city_id'],
            // A booking's own start is what a peak rule should price, not the
            // moment the fee happens to be resolved.
            occurredAt: $booking->starts_at ?? $booking->created_at ?? now(),
            successOperations: $record['success'],
            totalOperations: $record['total'],
            disputedOperations: $record['disputed'],
            isSubscribed: $this->isSubscribed($payerId),
        );
    }

    /**
     * Where the operation happens. The business's primary address anchors it —
     * the service is delivered at the business, so that is the geography a
     * governorate/city rule should price.
     *
     * @return array{governorate_id: ?int, city_id: ?int}
     */
    private function primaryLocationFor(int $businessId): array
    {
        if ($businessId <= 0) {
            return ['governorate_id' => null, 'city_id' => null];
        }

        $address = Address::query()
            ->where('user_id', $businessId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first(['governorate_id', 'city_id']);

        return [
            'governorate_id' => $address?->governorate_id ? (int) $address->governorate_id : null,
            'city_id' => $address?->city_id ? (int) $address->city_id : null,
        ];
    }

    /**
     * The payer's track record in the role they hold on this operation — a
     * business's history as a business, a client's as a client.
     *
     * @return array{success: int, total: int, disputed: int}
     */
    private function operationRecordFor(int $userId, string $payer): array
    {
        if ($userId <= 0) {
            return ['success' => 0, 'total' => 0, 'disputed' => 0];
        }

        $role = $payer === ServiceFeeRule::PAYER_BUSINESS
            ? UserOperationRating::ROLE_BUSINESS
            : UserOperationRating::ROLE_CLIENT;

        $row = UserOperationRating::query()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->first(['success_count', 'total_operations', 'disputed_count']);

        return [
            'success' => (int) ($row->success_count ?? 0),
            'total' => (int) ($row->total_operations ?? 0),
            'disputed' => (int) ($row->disputed_count ?? 0),
        ];
    }

    private function isSubscribed(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return Subscription::query()
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->exists();
    }
}
