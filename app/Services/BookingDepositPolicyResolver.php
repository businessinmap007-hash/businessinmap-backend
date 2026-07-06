<?php

namespace App\Services;

use App\Models\BusinessDepositPolicy;
use App\Models\PlatformService;
use App\Models\User;

class BookingDepositPolicyResolver
{
    /**
     * Deposit is single-source: resolved from the business deposit policy (per
     * business + child + service). Physical bookable units no longer carry any
     * deposit override — a premium unit is modelled as a distinct item type.
     */
    public function resolve(User $business, PlatformService $service): array
    {
        $policy = $this->findBusinessPolicy((int) $business->id, (int) $service->id, (int) ($business->category_child_id ?? 0));

        if (! $policy) {
            return $this->disabledPolicy('no_business_policy');
        }

        return $this->fromBusinessPolicy($policy);
    }

    protected function findBusinessPolicy(int $businessId, int $serviceId, int $categoryChildId): ?BusinessDepositPolicy
    {
        return BusinessDepositPolicy::query()
            ->enabled()
            ->where('business_id', $businessId)
            ->orderBy('priority')
            ->get()
            ->first(function (BusinessDepositPolicy $policy) use ($serviceId, $categoryChildId) {
                return match ($policy->scope_key) {
                    BusinessDepositPolicy::SCOPE_BUSINESS_CHILD_SERVICE => (int) $policy->platform_service_id === $serviceId && (int) $policy->category_child_id === $categoryChildId,
                    BusinessDepositPolicy::SCOPE_BUSINESS_CHILD => (int) $policy->category_child_id === $categoryChildId,
                    BusinessDepositPolicy::SCOPE_BUSINESS_SERVICE => (int) $policy->platform_service_id === $serviceId,
                    BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL => true,
                    default => false,
                };
            });
    }

    protected function fromBusinessPolicy(BusinessDepositPolicy $policy): array
    {
        return [
            'enabled' => (bool) $policy->is_enabled,
            'scope_key' => $policy->scope_key,
            'priority' => (int) $policy->priority,
            'deposit_mode' => $policy->deposit_mode,
            'calculation_base' => $policy->calculation_base,
            'deposit_type' => $policy->deposit_type,
            'deposit_value' => (float) $policy->deposit_value,
            'max_deposit_percent' => (float) $policy->max_deposit_percent,
            'min_deposit_amount' => $policy->min_deposit_amount !== null ? (float) $policy->min_deposit_amount : null,
            'max_deposit_amount' => $policy->max_deposit_amount !== null ? (float) $policy->max_deposit_amount : null,
            'wallet_hold_enabled' => (bool) $policy->wallet_hold_enabled,
            'external_verification_enabled' => (bool) $policy->external_verification_enabled,
            'business_counter_hold_enabled' => (bool) $policy->business_counter_hold_enabled,
            'business_counter_hold_percent' => (float) $policy->business_counter_hold_percent,
            'client_guarantee_strategy' => (string) ($policy->client_guarantee_strategy ?? BusinessDepositPolicy::GUARANTEE_PER_OPERATION_HOLD),
            'business_guarantee_strategy' => (string) ($policy->business_guarantee_strategy ?? BusinessDepositPolicy::GUARANTEE_PER_OPERATION_HOLD),
            'guarantee_hybrid_extra_percent' => (float) ($policy->guarantee_hybrid_extra_percent ?? 20),
            'dispute_resolution_days' => (int) $policy->dispute_resolution_days,
            'warning_every_days' => (int) $policy->warning_every_days,
            'non_cooperation_fee_enabled' => (bool) $policy->non_cooperation_fee_enabled,
            'non_cooperation_fee_type' => $policy->non_cooperation_fee_type,
            'non_cooperation_fee_value' => (float) $policy->non_cooperation_fee_value,
            'currency' => $policy->currency,
            'source' => 'business_deposit_policy',
            'policy_id' => (int) $policy->id,
        ];
    }

    protected function disabledPolicy(string $source): array
    {
        return [
            'enabled' => false,
            'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_FIRST_DAY,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 0,
            'max_deposit_percent' => 20,
            'min_deposit_amount' => null,
            'max_deposit_amount' => null,
            'wallet_hold_enabled' => false,
            'external_verification_enabled' => false,
            'business_counter_hold_enabled' => false,
            'business_counter_hold_percent' => 50,
            'client_guarantee_strategy' => BusinessDepositPolicy::GUARANTEE_PER_OPERATION_HOLD,
            'business_guarantee_strategy' => BusinessDepositPolicy::GUARANTEE_PER_OPERATION_HOLD,
            'guarantee_hybrid_extra_percent' => 20,
            'currency' => 'EGP',
            'source' => $source,
        ];
    }
}
