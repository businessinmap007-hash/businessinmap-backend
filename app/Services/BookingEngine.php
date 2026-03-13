<?php

namespace App\Services;

use App\Models\PlatformService;
use App\Models\BusinessServicePrice;
use Illuminate\Validation\ValidationException;

class BookingEngine
{
    public function prepare(int $businessId, int $serviceId): array
    {
        $service = PlatformService::query()->findOrFail($serviceId);

        $businessPrice = BusinessServicePrice::query()
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->first();

        if (!$businessPrice) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة لهذا البزنس.',
            ]);
        }

        $price = round((float) $businessPrice->price, 2);

        $platformFee = $this->calculatePlatformFee($service, $price);
        $deposit = $this->calculateDeposit($service, $businessPrice, $price);

        return [
            'service' => $service,
            'business_price' => $businessPrice,
            'price' => $price,
            'platform_fee' => $platformFee,
            'deposit' => $deposit,
        ];
    }

    protected function calculatePlatformFee(PlatformService $service, float $price): float
    {
        $feeType = (string) ($service->fee_type ?? '');
        $feeValue = (float) ($service->fee_value ?? 0);

        if ($feeValue <= 0 || $feeType === '') {
            return 0.00;
        }

        if ($feeType === 'percent') {
            return round($price * ($feeValue / 100), 2);
        }

        if ($feeType === 'fixed') {
            return round($feeValue, 2);
        }

        return 0.00;
    }

    protected function calculateDeposit(
        PlatformService $service,
        BusinessServicePrice $businessPrice,
        float $price
    ): float {
        if (!(bool) $service->supports_deposit) {
            return 0.00;
        }

        if (!(bool) $businessPrice->deposit_enabled) {
            return 0.00;
        }

        $percent = (int) ($businessPrice->deposit_percent ?? 0);
        $maxPercent = (int) ($service->max_deposit_percent ?? 0);

        if ($percent <= 0) {
            return 0.00;
        }

        if ($maxPercent > 0 && $percent > $maxPercent) {
            $percent = $maxPercent;
        }

        return round($price * ($percent / 100), 2);
    }
}