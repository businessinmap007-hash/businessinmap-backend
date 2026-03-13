<?php

namespace App\Services;

use App\Models\ServiceFee;

class ServiceFeeService
{
    public function getByCode(string $code): ?ServiceFee
    {
        return ServiceFee::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Prefer fee with matching service_id, else fallback to service_id NULL.
     */
    public function getByCodeForService(string $code, ?int $serviceId): ?ServiceFee
    {
        return ServiceFee::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->where(function ($q) use ($serviceId) {
                if ($serviceId) {
                    $q->where('service_id', $serviceId)->orWhereNull('service_id');
                } else {
                    $q->whereNull('service_id');
                }
            })
            ->orderByRaw('service_id IS NULL') // service_id NOT NULL first, then NULL
            ->first();
    }

    /**
     * Split fee as fixed amounts for each side (EGP values).
     *
     * rules supports:
     *  - client_amount
     *  - business_amount
     *
     * If rules empty, fallback to column `amount` for BOTH sides (optional fallback).
     *
     * @return array{0: float, 1: float} [clientFee, businessFee]
     */
    public function resolveSplit(ServiceFee $fee, float $baseAmount = 0.0): array
    {
        $rules = $this->normalizeRules($fee->rules);

        $client = 0.0;
        $business = 0.0;

        if (array_key_exists('client_amount', $rules) || array_key_exists('business_amount', $rules)) {
            $client = (float) ($rules['client_amount'] ?? 0);
            $business = (float) ($rules['business_amount'] ?? 0);
        } else {
            // fallback: use column amount for both sides (only if you want a simple default)
            $client = (float) ($fee->amount ?? 0);
            $business = (float) ($fee->amount ?? 0);
        }

        if ($client < 0) $client = 0;
        if ($business < 0) $business = 0;

        return [round($client, 2), round($business, 2)];
    }

    /**
     * General fee calculator (fixed/percent/tiered) for other modules if needed.
     */
    public function calculate(string $code, float $baseAmount, array $context = []): float
    {
        $feeRow = $this->getByCode($code);
        if (!$feeRow) {
            return 0.0;
        }

        $rules = $this->normalizeRules($feeRow->rules);

        // fallback: fixed amount from column
        if (empty($rules)) {
            return round((float) $feeRow->amount, 2);
        }

        $type = $rules['type'] ?? 'fixed';

        $result = match ($type) {
            'percent' => $this->percentFee($rules, $baseAmount),
            'tiered'  => $this->tieredFee($rules, $baseAmount),
            'fixed'   => $this->fixedFee($rules, (float) $feeRow->amount),
            default   => $this->fixedFee($rules, (float) $feeRow->amount),
        };

        if ($result < 0) $result = 0;

        return round($result, 2);
    }

    /**
     * Ensure rules always array.
     */
    protected function normalizeRules($rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        if (is_null($rules) || $rules === '') {
            return [];
        }

        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function fixedFee(array $rules, float $fallbackAmount): float
    {
        if (isset($rules['amount']) && is_numeric($rules['amount'])) {
            return (float) $rules['amount'];
        }
        return $fallbackAmount;
    }

    protected function percentFee(array $rules, float $baseAmount): float
    {
        $percent = (float) ($rules['percent'] ?? 0);
        $fee = ($baseAmount * $percent) / 100;

        if (isset($rules['min']) && is_numeric($rules['min'])) {
            $fee = max($fee, (float) $rules['min']);
        }
        if (isset($rules['max']) && is_numeric($rules['max'])) {
            $fee = min($fee, (float) $rules['max']);
        }

        return $fee;
    }

    protected function tieredFee(array $rules, float $baseAmount): float
    {
        $tiers = $rules['tiers'] ?? [];
        if (!is_array($tiers)) {
            return 0.0;
        }

        foreach ($tiers as $tier) {
            if (!is_array($tier)) continue;

            $min = isset($tier['min']) ? (float) $tier['min'] : 0.0;
            $max = array_key_exists('max', $tier) ? $tier['max'] : null;

            $maxOk = is_null($max) || $baseAmount < (float) $max;

            if ($baseAmount >= $min && $maxOk) {
                return (float) ($tier['amount'] ?? 0);
            }
        }

        return 0.0;
    }
}