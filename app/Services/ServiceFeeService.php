<?php

namespace App\Services;

use App\Models\ServiceFee;

class ServiceFeeService
{
    /**
     * Get active ServiceFee by code
     */
    public function getByCode(string $code): ?ServiceFee
    {
        return ServiceFee::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function getByCodeForService(string $code, ?int $serviceId): ?ServiceFee
    {
        return ServiceFee::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->when($serviceId, fn($q) => $q->where('service_id', $serviceId))
            ->orderByRaw('service_id is null') // يفضل الخاص بالخدمة إن وجد
            ->first();
    }

    /**
     * Return [clientFee, businessFee]
     * rules supports:
     *  - client_amount, business_amount
     *  - client_percent, business_percent (percent of booking price)
     */
    public function resolveSplit(ServiceFee $fee, float $bookingPrice): array
    {
        $rules = $fee->rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true) ?: [];
        }
        if (!is_array($rules)) $rules = [];

        $client = 0.0;
        $business = 0.0;

        if (isset($rules['client_amount']) || isset($rules['business_amount'])) {
            $client   = (float) ($rules['client_amount'] ?? 0);
            $business = (float) ($rules['business_amount'] ?? 0);
        } elseif (isset($rules['client_percent']) || isset($rules['business_percent'])) {
            $clientPct   = (float) ($rules['client_percent'] ?? 0);
            $businessPct = (float) ($rules['business_percent'] ?? 0);
            $client   = ($bookingPrice * $clientPct) / 100.0;
            $business = ($bookingPrice * $businessPct) / 100.0;
        } else {
            // fallback: لو amount موجود وعايز تقسيم متساوي (اختياري)
            $client = (float) $fee->amount;
            $business = (float) $fee->amount;
        }

        return [round($client, 2), round($business, 2)];
    }

    /**
     * Calculate service fee
     *
     * @param string $code
     * @param float  $baseAmount   (order total / wallet amount / deposit amount)
     * @param array  $context      optional (distance, items_count, user_type, ...)
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

        // Safety: never negative
        if ($result < 0) $result = 0;

        return round($result, 2);
    }

    /**
     * Ensure rules is always an array
     * - handles: array (casted), null, JSON string, invalid data
     */
    protected function normalizeRules($rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        if (is_null($rules) || $rules === '') {
            return [];
        }

        // If rules stored as JSON string
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Fixed fee: prefer rules['amount'], fallback to column amount
     */
    protected function fixedFee(array $rules, float $fallbackAmount): float
    {
        if (isset($rules['amount']) && is_numeric($rules['amount'])) {
            return (float) $rules['amount'];
        }

        return $fallbackAmount;
    }

    /**
     * Percent-based fee
     * rules:
     * - percent (required)
     * - min (optional)
     * - max (optional)
     */
    protected function percentFee(array $rules, float $baseAmount): float
    {
        $percent = (float) ($rules['percent'] ?? 0);
        $fee     = ($baseAmount * $percent) / 100;

        if (isset($rules['min']) && is_numeric($rules['min'])) {
            $fee = max($fee, (float) $rules['min']);
        }

        if (isset($rules['max']) && is_numeric($rules['max'])) {
            $fee = min($fee, (float) $rules['max']);
        }

        return $fee;
    }

    /**
     * Tiered fee
     * tiers: [
     *   {min: 0, max: 50, amount: 10},
     *   {min: 50, max: 100, amount: 15},
     *   {min: 100, max: null, amount: 20},
     * ]
     */
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

            $maxOk = is_null($max) || $baseAmount < (float)$max;

            if ($baseAmount >= $min && $maxOk) {
                return (float) ($tier['amount'] ?? 0);
            }
        }

        return 0.0;
    }
}
