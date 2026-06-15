<?php

namespace App\Services;


class BookingDepositCalculator
{
    public const SYSTEM_MAX_PERCENT = 20.0;
    public const DEFAULT_COUNTER_HOLD_PERCENT = 50.0;

    public function calculate(array $policy, array $amounts): array
    {
        $totalAmount = $this->money($amounts['total_amount'] ?? 0);
        $firstDayAmount = $this->money($amounts['first_day_amount'] ?? $totalAmount);

        if (! ($policy['enabled'] ?? false) || $totalAmount <= 0) {
            return $this->empty($policy, $totalAmount, $firstDayAmount);
        }

        $baseKey = ($policy['calculation_base'] ?? 'first_day') === 'total' ? 'total' : 'first_day';
        $baseAmount = $baseKey === 'total' ? $totalAmount : $firstDayAmount;

        $type = ($policy['deposit_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value = $this->money($policy['deposit_value'] ?? 0);
        $maxPercent = min(
            self::SYSTEM_MAX_PERCENT,
            max(0.0, (float) ($policy['max_deposit_percent'] ?? self::SYSTEM_MAX_PERCENT))
        );

        $configuredPercent = 0.0;
        if ($type === 'percent') {
            $configuredPercent = min(max($value, 0.0), $maxPercent);
            $depositAmount = $this->money($baseAmount * ($configuredPercent / 100));
        } else {
            $depositAmount = min($value, $this->money($baseAmount * ($maxPercent / 100)));
            $configuredPercent = $baseAmount > 0 ? round(($depositAmount / $baseAmount) * 100, 2) : 0.0;
        }

        if (($policy['min_deposit_amount'] ?? null) !== null) {
            $depositAmount = max($depositAmount, $this->money($policy['min_deposit_amount']));
        }

        if (($policy['max_deposit_amount'] ?? null) !== null) {
            $depositAmount = min($depositAmount, $this->money($policy['max_deposit_amount']));
        }

        $depositAmount = min($depositAmount, $this->money($baseAmount * ($maxPercent / 100)));
        $depositAmount = $this->money($depositAmount);

        if ($depositAmount <= 0) {
            return $this->empty($policy, $totalAmount, $firstDayAmount);
        }

        $mode = $policy['deposit_mode'] ?? 'wallet_hold';
        $walletHoldRequired = in_array($mode, ['wallet_hold', 'both'], true) && (bool) ($policy['wallet_hold_enabled'] ?? true);
        $externalRequired = in_array($mode, ['external_verification', 'both'], true) && (bool) ($policy['external_verification_enabled'] ?? false);

        $walletHoldAmount = $walletHoldRequired ? $depositAmount : 0.0;
        $externalAmount = $externalRequired ? $depositAmount : 0.0;

        $counterEnabled = $walletHoldRequired && (bool) ($policy['business_counter_hold_enabled'] ?? true);
        $counterPercent = $counterEnabled
            ? max(0.0, min((float) ($policy['business_counter_hold_percent'] ?? self::DEFAULT_COUNTER_HOLD_PERCENT), 100.0))
            : 0.0;
        $counterAmount = $this->money($walletHoldAmount * ($counterPercent / 100));

        return [
            'enabled' => true,
            'required' => $walletHoldRequired || $externalRequired,
            'mode' => $mode,
            'calculation_base' => $baseKey,
            'deposit_type' => $type,
            'deposit_value' => $value,
            'configured_percent' => $configuredPercent,
            'max_deposit_percent' => $maxPercent,
            'total_amount' => $totalAmount,
            'first_day_amount' => $firstDayAmount,
            'base_amount' => $baseAmount,
            'amount' => $depositAmount,
            'hold' => $walletHoldAmount,
            'wallet_hold_required' => $walletHoldRequired,
            'wallet_hold_amount' => $walletHoldAmount,
            'external_deposit_required' => $externalRequired,
            'external_deposit_amount' => $externalAmount,
            'business_counter_hold_required' => $counterEnabled && $counterAmount > 0,
            'business_counter_hold_percent' => $counterPercent,
            'business_counter_hold_amount' => $counterAmount,
            'remaining_amount_before_external' => $totalAmount,
            'remaining_amount_after_external_if_verified' => $this->money(max($totalAmount - $externalAmount, 0)),
            'currency' => $policy['currency'] ?? 'EGP',
            'source' => $policy['source'] ?? 'business_deposit_policy',
            'policy' => $policy,
        ];
    }

    protected function empty(array $policy, float $totalAmount, float $firstDayAmount): array
    {
        return [
            'enabled' => false,
            'required' => false,
            'mode' => $policy['deposit_mode'] ?? 'wallet_hold',
            'calculation_base' => $policy['calculation_base'] ?? 'first_day',
            'deposit_type' => $policy['deposit_type'] ?? 'percent',
            'deposit_value' => (float) ($policy['deposit_value'] ?? 0),
            'configured_percent' => 0,
            'max_deposit_percent' => (float) ($policy['max_deposit_percent'] ?? self::SYSTEM_MAX_PERCENT),
            'total_amount' => $totalAmount,
            'first_day_amount' => $firstDayAmount,
            'base_amount' => 0.0,
            'amount' => 0.0,
            'hold' => 0.0,
            'wallet_hold_required' => false,
            'wallet_hold_amount' => 0.0,
            'external_deposit_required' => false,
            'external_deposit_amount' => 0.0,
            'business_counter_hold_required' => false,
            'business_counter_hold_percent' => 0.0,
            'business_counter_hold_amount' => 0.0,
            'remaining_amount_before_external' => $totalAmount,
            'remaining_amount_after_external_if_verified' => $totalAmount,
            'currency' => $policy['currency'] ?? 'EGP',
            'source' => $policy['source'] ?? 'none',
            'policy' => $policy,
        ];
    }

    protected function money(mixed $value): float
    {
        return round(max((float) $value, 0), 2);
    }
}
