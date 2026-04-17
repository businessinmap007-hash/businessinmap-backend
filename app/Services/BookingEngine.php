<?php

namespace App\Services;

use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingEngine
{
    public function prepare(int $businessId, int $serviceId): array
    {
        $service = PlatformService::query()->findOrFail($serviceId);

        [$business, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        $businessPrice = $this->resolveBusinessPrice(
            businessId: $businessId,
            serviceId: $serviceId,
            childId: $childId
        );

        if (! $businessPrice) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة لهذا البزنس ضمن القسم الفرعي الحالي.',
            ]);
        }

        $price = round((float) $businessPrice->price, 2);

        $platformFee = $this->calculatePlatformFee(
            service: $service,
            price: $price
        );

        $deposit = $this->calculateDeposit(
            service: $service,
            businessPrice: $businessPrice,
            price: $price
        );

        return [
            'service' => $service,
            'business' => $business,
            'business_child_id' => $childId,
            'business_price' => $businessPrice,
            'price' => $price,
            'platform_fee' => $platformFee,
            'deposit' => $deposit,
        ];
    }

    protected function resolveBusinessContext(int $businessId): array
    {
        if ($businessId <= 0) {
            return [null, 0];
        }

        $business = User::query()
            ->select(['id', 'type', 'category_child_id'])
            ->where('id', $businessId)
            ->where('type', 'business')
            ->first();

        if (! $business) {
            return [null, 0];
        }

        return [
            $business,
            (int) ($business->category_child_id ?? 0),
        ];
    }

    protected function resolveBusinessPrice(int $businessId, int $serviceId, int $childId = 0): ?BusinessServicePrice
    {
        $hasChildColumn = Schema::hasColumn('business_service_prices', 'child_id');

        if ($hasChildColumn && $childId > 0) {
            $row = BusinessServicePrice::query()
                ->where('business_id', $businessId)
                ->where('child_id', $childId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return $row;
            }
        }

        return BusinessServicePrice::query()
            ->where('business_id', $businessId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
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
        if (! (bool) $service->supports_deposit) {
            return 0.00;
        }

        if (! (bool) $businessPrice->deposit_enabled) {
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