<?php

namespace App\Services;

use App\Models\PlatformServiceFeePromotion;

class PlatformServiceFeePromotionService
{
    public function apply(
        ?int $serviceId,
        ?int $childId,
        float $businessFee,
        float $clientFee
    ): array {
        $businessFee = max(0, $businessFee);
        $clientFee = max(0, $clientFee);

        $promotion = PlatformServiceFeePromotion::query()
            ->active()
            ->currentlyRunning()
            ->forServiceAndChild($serviceId, $childId)
            ->orderedForApply()
            ->first();

        $finalBusinessFee = $businessFee;
        $finalClientFee = $clientFee;

        $businessDiscount = 0.0;
        $clientDiscount = 0.0;

        if ($promotion) {
            if ($promotion->isForBusiness()) {
                $before = $finalBusinessFee;
                $finalBusinessFee = $this->applyDiscount(
                    $finalBusinessFee,
                    (string) $promotion->discount_type,
                    $promotion->discount_value
                );
                $businessDiscount = max(0, $before - $finalBusinessFee);
            }

            if ($promotion->isForClient()) {
                $before = $finalClientFee;
                $finalClientFee = $this->applyDiscount(
                    $finalClientFee,
                    (string) $promotion->discount_type,
                    $promotion->discount_value
                );
                $clientDiscount = max(0, $before - $finalClientFee);
            }
        }

        return [
            'original_business_fee' => round($businessFee, 2),
            'original_client_fee'   => round($clientFee, 2),

            'final_business_fee' => round($finalBusinessFee, 2),
            'final_client_fee'   => round($finalClientFee, 2),

            'platform_promotion_applied' => (bool) $promotion,

            'platform_promotion' => $promotion ? [
                'id'             => $promotion->id,
                'name'           => $promotion->name,
                'scope_type'     => $promotion->scope_type,
                'target_party'   => $promotion->target_party,
                'discount_type'  => $promotion->discount_type,
                'discount_value' => $promotion->discount_value !== null
                    ? (float) $promotion->discount_value
                    : null,
                'starts_at'      => optional($promotion->starts_at)->toDateTimeString(),
                'ends_at'        => optional($promotion->ends_at)->toDateTimeString(),
            ] : null,

            'platform_discount_business_fee' => round($businessDiscount, 2),
            'platform_discount_client_fee'   => round($clientDiscount, 2),

            'platform_discount_total' => round($businessDiscount + $clientDiscount, 2),
        ];
    }

    private function applyDiscount(float $currentFee, string $discountType, $discountValue): float
    {
        $currentFee = max(0, $currentFee);
        $value = $discountValue !== null && $discountValue !== ''
            ? (float) $discountValue
            : 0.0;

        return match ($discountType) {
            PlatformServiceFeePromotion::DISCOUNT_WAIVE => 0.0,

            PlatformServiceFeePromotion::DISCOUNT_OVERRIDE_TO_FIXED => max(0, $value),

            PlatformServiceFeePromotion::DISCOUNT_FIXED_DISCOUNT => max(0, $currentFee - $value),

            PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT => max(
                0,
                $currentFee - (($currentFee * max(0, $value)) / 100)
            ),

            default => $currentFee,
        };
    }
}