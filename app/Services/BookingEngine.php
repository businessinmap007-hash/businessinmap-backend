<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlatformService;
use App\Models\BusinessServicePrice;

class BookingEngine
{
    public function prepare(int $businessId, int $serviceId)
    {
        $service = PlatformService::findOrFail($serviceId);

        $businessPrice = BusinessServicePrice::where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->firstOrFail();

        $price = (float) $businessPrice->price;

        $platformFee = $this->calculatePlatformFee($service, $price);

        $deposit = $this->calculateDeposit($service, $businessPrice, $price);

        return [
            'price' => $price,
            'platform_fee' => $platformFee,
            'deposit' => $deposit,
        ];
    }

    protected function calculatePlatformFee($service, float $price)
    {
        if (!$service->fee_type) {
            return 0;
        }

        if ($service->fee_type === 'percent') {
            return round($price * ($service->fee_value / 100), 2);
        }

        if ($service->fee_type === 'fixed') {
            return (float) $service->fee_value;
        }

        return 0;
    }

    protected function calculateDeposit($service, $businessPrice, float $price)
    {
        if (!$service->supports_deposit) {
            return 0;
        }

        if (!$businessPrice->deposit_enabled) {
            return 0;
        }

        $percent = (int) $businessPrice->deposit_percent;

        if ($percent <= 0) {
            return 0;
        }

        return round($price * ($percent / 100), 2);
    }
}