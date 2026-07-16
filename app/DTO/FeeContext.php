<?php

namespace App\DTO;

use Carbon\CarbonInterface;

/**
 * BIM-3.5 — everything a fee rule is allowed to know about one operation.
 *
 * Immutable and query-free by design: whoever builds it does the lookups once
 * (ServiceFeeRuleEngine::contextForBooking), so rule matching stays pure and
 * cheap no matter how many rules run. If a rule needs a new fact, it gets added
 * here rather than the rule reaching into the database mid-evaluation.
 */
final class FeeContext
{
    public function __construct(
        /** Which side is being charged: business|client. */
        public readonly string $payer,
        public readonly string $feeCode,
        public readonly float $baseAmount,
        public readonly ?int $serviceId = null,
        public readonly ?string $serviceKey = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $childId = null,
        public readonly ?int $businessId = null,
        public readonly ?int $clientId = null,
        /** Where the operation happens — the business's primary address. */
        public readonly ?int $governorateId = null,
        public readonly ?int $cityId = null,
        /** When it happens (booking start), which is what peak rules test. */
        public readonly ?CarbonInterface $occurredAt = null,
        /** The payer's own track record, in the role they hold here. */
        public readonly int $successOperations = 0,
        public readonly int $totalOperations = 0,
        public readonly int $disputedOperations = 0,
        /** Whether the payer holds an active subscription. */
        public readonly bool $isSubscribed = false,
    ) {}

    /** 0=Sunday .. 6=Saturday, matching the platform's convention. */
    public function dayOfWeek(): ?int
    {
        return $this->occurredAt?->dayOfWeek;
    }

    /** "HH:MM" for time-window comparisons. */
    public function timeOfDay(): ?string
    {
        return $this->occurredAt?->format('H:i');
    }

    /** A snapshot for the fee line's trace — why the fee came out as it did. */
    public function toArray(): array
    {
        return [
            'payer' => $this->payer,
            'fee_code' => $this->feeCode,
            'base_amount' => round($this->baseAmount, 2),
            'service_id' => $this->serviceId,
            'service_key' => $this->serviceKey,
            'category_id' => $this->categoryId,
            'child_id' => $this->childId,
            'governorate_id' => $this->governorateId,
            'city_id' => $this->cityId,
            'occurred_at' => $this->occurredAt?->toDateTimeString(),
            'day_of_week' => $this->dayOfWeek(),
            'time_of_day' => $this->timeOfDay(),
            'success_operations' => $this->successOperations,
            'total_operations' => $this->totalOperations,
            'disputed_operations' => $this->disputedOperations,
            'is_subscribed' => $this->isSubscribed,
        ];
    }
}
