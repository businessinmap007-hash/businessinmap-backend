<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BusinessDepositPolicy;
use App\Models\PlatformService;
use App\Models\User;

class BookingDepositPolicyResolver
{
    public function resolve(
        User $business,
        PlatformService $service,
        ?BookableItem $bookable = null
    ): array {
        if (! (bool) ($service->supports_deposit ?? false)) {
            return $this->disabled('service_not_supported');
        }

        $childId = $this->resolveChildId($business, $bookable);
        $serviceId = (int) $service->id;

        $businessPolicy = $this->resolveBusinessPolicy(
            businessId: (int) $business->id,
            serviceId: $serviceId,
            childId: $childId
        );

        $base = $businessPolicy
            ? $this->fromBusinessPolicy($businessPolicy)
            : $this->legacyFallbackFromUser($business);

        if ($bookable) {
            $mode = (string) ($bookable->deposit_policy_mode ?? 'inherit');

            if ($mode === 'disabled') {
                return $this->disabled('bookable_disabled');
            }

            if ($mode === 'custom') {
                return $this->fromBookableOverride($bookable, $base);
            }
        }

        return $base;
    }

    protected function resolveBusinessPolicy(int $businessId, int $serviceId, ?int $childId = null): ?BusinessDepositPolicy
    {
        /*
         |--------------------------------------------------------------------------
         | Priority:
         |--------------------------------------------------------------------------
         | 1) business + child + service
         | 2) business + service
         | 3) business + child
         | 4) business global
         |--------------------------------------------------------------------------
         */

        $candidates = BusinessDepositPolicy::query()
            ->enabled()
            ->forBusiness($businessId)
            ->where(function ($q) use ($serviceId) {
                $q->whereNull('platform_service_id')
                    ->orWhere('platform_service_id', $serviceId);
            })
            ->where(function ($q) use ($childId) {
                $q->whereNull('category_child_id');

                if ($childId) {
                    $q->orWhere('category_child_id', $childId);
                }
            })
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(function (BusinessDepositPolicy $policy) use ($serviceId, $childId) {
                $hasService = (int) ($policy->platform_service_id ?? 0) === $serviceId;
                $hasChild = $childId && (int) ($policy->category_child_id ?? 0) === $childId;

                $scopeRank = match (true) {
                    $hasService && $hasChild => 1,
                    $hasService => 2,
                    $hasChild => 3,
                    default => 4,
                };

                return sprintf('%02d-%06d-%010d', $scopeRank, (int) ($policy->priority ?? 100), (int) $policy->id);
            })
            ->first();
    }

    protected function resolveChildId(User $business, ?BookableItem $bookable = null): ?int
    {
        $bookableChildId = (int) (
            $bookable->category_child_id
            ?? $bookable->child_id
            ?? 0
        );

        if ($bookableChildId > 0) {
            return $bookableChildId;
        }

        $businessChildId = (int) ($business->category_child_id ?? 0);

        return $businessChildId > 0 ? $businessChildId : null;
    }

    protected function fromBusinessPolicy(BusinessDepositPolicy $policy): array
    {
        $serviceScoped = ! empty($policy->platform_service_id);
        $childScoped = ! empty($policy->category_child_id);

        return [
            'enabled' => true,

            'deposit_mode' => (string) ($policy->deposit_mode ?: BusinessDepositPolicy::MODE_WALLET_HOLD),
            'calculation_base' => (string) ($policy->calculation_base ?: BusinessDepositPolicy::BASE_FIRST_DAY),
            'deposit_type' => (string) ($policy->deposit_type ?: BusinessDepositPolicy::TYPE_PERCENT),
            'deposit_value' => (float) ($policy->deposit_value ?? 20),
            'max_deposit_percent' => (float) ($policy->max_deposit_percent ?? 20),
            'min_deposit_amount' => $policy->min_deposit_amount !== null ? (float) $policy->min_deposit_amount : null,
            'max_deposit_amount' => $policy->max_deposit_amount !== null ? (float) $policy->max_deposit_amount : null,

            'external_verification_enabled' => (bool) $policy->external_verification_enabled,
            'wallet_hold_enabled' => (bool) $policy->wallet_hold_enabled,
            'business_counter_hold_enabled' => (bool) $policy->business_counter_hold_enabled,
            'business_counter_hold_percent' => (float) ($policy->business_counter_hold_percent ?? 50),

            'dispute_resolution_days' => max((int) ($policy->dispute_resolution_days ?? 15), 1),
            'warning_every_days' => max((int) ($policy->warning_every_days ?? 3), 1),

            'non_cooperation_fee_enabled' => (bool) $policy->non_cooperation_fee_enabled,
            'non_cooperation_fee_type' => $policy->non_cooperation_fee_type,
            'non_cooperation_fee_value' => $policy->non_cooperation_fee_value !== null ? (float) $policy->non_cooperation_fee_value : null,

            'currency' => (string) ($policy->currency ?: 'EGP'),

            'source' => $this->sourceForPolicy($serviceScoped, $childScoped),
            'scope_key' => (string) ($policy->scope_key ?: BusinessDepositPolicy::resolveScopeKey(
                $policy->platform_service_id,
                $policy->category_child_id
            )),
            'business_deposit_policy_id' => (int) $policy->id,
            'policy_business_id' => (int) $policy->business_id,
            'policy_platform_service_id' => $policy->platform_service_id ? (int) $policy->platform_service_id : null,
            'policy_category_child_id' => $policy->category_child_id ? (int) $policy->category_child_id : null,
            'policy_priority' => (int) ($policy->priority ?? 100),
        ];
    }

    protected function sourceForPolicy(bool $serviceScoped, bool $childScoped): string
    {
        return match (true) {
            $serviceScoped && $childScoped => 'business_child_service_deposit_policy',
            $serviceScoped => 'business_service_deposit_policy',
            $childScoped => 'business_child_deposit_policy',
            default => 'business_global_deposit_policy',
        };
    }

    protected function legacyFallbackFromUser(User $business): array
    {
        if (method_exists($business, 'requiresBookingHold') && $business->requiresBookingHold()) {
            return [
                'enabled' => true,
                'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
                'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
                'deposit_type' => BusinessDepositPolicy::TYPE_FIXED,
                'deposit_value' => (float) $business->bookingHoldAmount(),
                'max_deposit_percent' => 20.0,
                'min_deposit_amount' => null,
                'max_deposit_amount' => null,

                'external_verification_enabled' => false,
                'wallet_hold_enabled' => true,
                'business_counter_hold_enabled' => true,
                'business_counter_hold_percent' => 50.0,

                'dispute_resolution_days' => 15,
                'warning_every_days' => 3,

                'non_cooperation_fee_enabled' => false,
                'non_cooperation_fee_type' => null,
                'non_cooperation_fee_value' => null,

                'currency' => 'EGP',
                'source' => 'users.booking_hold_legacy',
                'scope_key' => 'legacy_user_booking_hold',
            ];
        }

        return $this->disabled('no_business_policy');
    }

    protected function fromBookableOverride(BookableItem $bookable, array $base): array
    {
        $base['enabled'] = true;
        $base['deposit_mode'] = $bookable->deposit_mode ?: ($base['deposit_mode'] ?? BusinessDepositPolicy::MODE_WALLET_HOLD);
        $base['calculation_base'] = $bookable->deposit_calculation_base ?: ($base['calculation_base'] ?? BusinessDepositPolicy::BASE_FIRST_DAY);
        $base['deposit_type'] = $bookable->deposit_type ?: ($base['deposit_type'] ?? BusinessDepositPolicy::TYPE_PERCENT);

        $base['deposit_value'] = $bookable->deposit_value !== null
            ? (float) $bookable->deposit_value
            : (float) ($base['deposit_value'] ?? 20);

        $base['max_deposit_percent'] = $bookable->max_deposit_percent !== null
            ? (float) $bookable->max_deposit_percent
            : (float) ($base['max_deposit_percent'] ?? 20);

        $base['min_deposit_amount'] = $bookable->min_deposit_amount !== null
            ? (float) $bookable->min_deposit_amount
            : ($base['min_deposit_amount'] ?? null);

        $base['max_deposit_amount'] = $bookable->max_deposit_amount !== null
            ? (float) $bookable->max_deposit_amount
            : ($base['max_deposit_amount'] ?? null);

        $base['external_verification_enabled'] = $bookable->external_verification_enabled !== null
            ? (bool) $bookable->external_verification_enabled
            : (bool) ($base['external_verification_enabled'] ?? false);

        $base['wallet_hold_enabled'] = $bookable->wallet_hold_enabled !== null
            ? (bool) $bookable->wallet_hold_enabled
            : (bool) ($base['wallet_hold_enabled'] ?? true);

        $base['business_counter_hold_enabled'] = $bookable->business_counter_hold_enabled !== null
            ? (bool) $bookable->business_counter_hold_enabled
            : (bool) ($base['business_counter_hold_enabled'] ?? true);

        $base['business_counter_hold_percent'] = $bookable->business_counter_hold_percent !== null
            ? (float) $bookable->business_counter_hold_percent
            : (float) ($base['business_counter_hold_percent'] ?? 50);

        $base['source'] = 'bookable_item_override';
        $base['scope_key'] = 'bookable_item_override';
        $base['bookable_item_id'] = (int) $bookable->id;

        return $base;
    }

    protected function disabled(string $source): array
    {
        return [
            'enabled' => false,
            'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_FIRST_DAY,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 0.0,
            'max_deposit_percent' => 20.0,
            'min_deposit_amount' => null,
            'max_deposit_amount' => null,

            'external_verification_enabled' => false,
            'wallet_hold_enabled' => false,
            'business_counter_hold_enabled' => false,
            'business_counter_hold_percent' => 0.0,

            'dispute_resolution_days' => 15,
            'warning_every_days' => 3,

            'non_cooperation_fee_enabled' => false,
            'non_cooperation_fee_type' => null,
            'non_cooperation_fee_value' => null,

            'currency' => 'EGP',
            'source' => $source,
            'scope_key' => 'disabled',
        ];
    }
}