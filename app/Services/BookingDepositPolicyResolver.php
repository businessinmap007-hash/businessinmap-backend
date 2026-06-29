<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BusinessDepositPolicy;
use App\Models\PlatformService;
use App\Models\User;

class BookingDepositPolicyResolver
{
    public function resolve(User $business, PlatformService $service, ?BookableItem $bookableItem = null): array
    {
        if ($bookableItem && $bookableItem->deposit_policy_mode === 'disabled') {
            return $this->disabledPolicy('bookable_disabled');
        }

        $policy = $this->findBusinessPolicy((int) $business->id, (int) $service->id, (int) ($business->category_child_id ?? 0));

        if (! $policy) {
            return ($bookableItem && (bool) $bookableItem->deposit_enabled)
                ? $this->bookablePolicy($bookableItem)
                : $this->disabledPolicy('no_business_policy');
        }

        $resolved = $this->fromBusinessPolicy($policy);

        if ($bookableItem && ($bookableItem->deposit_policy_mode === 'custom' || ((bool) $bookableItem->deposit_enabled && (float) ($bookableItem->deposit_percent ?? 0) > 0))) {
            return $this->applyBookableOverride($resolved, $bookableItem);
        }

        return $resolved;
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
            'min_deposit_amount' => (float) $policy->min_deposit_amount,
            'max_deposit_amount' => (float) $policy->max_deposit_amount,
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

    protected function bookablePolicy(BookableItem $item): array
    {
        return [
            'enabled' => true,
            'scope_key' => 'bookable_item',
            'priority' => 0,
            'deposit_mode' => $item->deposit_mode ?: BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => $item->deposit_calculation_base ?: BusinessDepositPolicy::BASE_FIRST_DAY,
            'deposit_type' => $item->deposit_type ?: BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => (float) ($item->deposit_value ?? $item->deposit_percent ?? 0),
            'max_deposit_percent' => (float) ($item->max_deposit_percent ?? 20),
            'min_deposit_amount' => (float) ($item->min_deposit_amount ?? 0),
            'max_deposit_amount' => (float) ($item->max_deposit_amount ?? 0),
            'wallet_hold_enabled' => $item->wallet_hold_enabled !== null ? (bool) $item->wallet_hold_enabled : true,
            'external_verification_enabled' => (bool) ($item->external_verification_enabled ?? false),
            'business_counter_hold_enabled' => $item->business_counter_hold_enabled !== null ? (bool) $item->business_counter_hold_enabled : true,
            'business_counter_hold_percent' => (float) ($item->business_counter_hold_percent ?? 50),
            'client_guarantee_strategy' => BusinessDepositPolicy::GUARANTEE_PER_OPERATION_HOLD,
            'business_guarantee_strategy' => BusinessDepositPolicy::GUARANTEE_PER_OPERATION_HOLD,
            'guarantee_hybrid_extra_percent' => 20,
            'dispute_resolution_days' => 15,
            'warning_every_days' => 3,
            'non_cooperation_fee_enabled' => false,
            'non_cooperation_fee_type' => 'fixed',
            'non_cooperation_fee_value' => 0,
            'currency' => 'EGP',
            'source' => 'bookable_item',
            'bookable_item_id' => (int) $item->id,
        ];
    }

    protected function applyBookableOverride(array $policy, BookableItem $item): array
    {
        if (! $item->deposit_enabled) {
            return $this->disabledPolicy('bookable_disabled');
        }

        $policy['deposit_mode'] = $item->deposit_mode ?: ($policy['deposit_mode'] ?? BusinessDepositPolicy::MODE_WALLET_HOLD);
        $policy['deposit_type'] = $item->deposit_type ?: BusinessDepositPolicy::TYPE_PERCENT;
        $policy['deposit_value'] = (float) ($item->deposit_value ?? $item->deposit_percent ?? $policy['deposit_value'] ?? 0);
        $policy['calculation_base'] = $item->deposit_calculation_base ?: ($policy['calculation_base'] ?? BusinessDepositPolicy::BASE_FIRST_DAY);
        $policy['max_deposit_percent'] = (float) ($item->max_deposit_percent ?? $policy['max_deposit_percent'] ?? 20);
        $policy['wallet_hold_enabled'] = $item->wallet_hold_enabled !== null ? (bool) $item->wallet_hold_enabled : ($policy['wallet_hold_enabled'] ?? true);
        $policy['external_verification_enabled'] = $item->external_verification_enabled !== null ? (bool) $item->external_verification_enabled : ($policy['external_verification_enabled'] ?? false);
        $policy['business_counter_hold_enabled'] = $item->business_counter_hold_enabled !== null ? (bool) $item->business_counter_hold_enabled : ($policy['business_counter_hold_enabled'] ?? true);
        $policy['business_counter_hold_percent'] = $item->business_counter_hold_percent !== null ? (float) $item->business_counter_hold_percent : ($policy['business_counter_hold_percent'] ?? 50);
        $policy['source'] = 'bookable_custom';
        $policy['bookable_item_id'] = (int) $item->id;

        return $policy;
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
            'min_deposit_amount' => 0,
            'max_deposit_amount' => 0,
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
