<?php

namespace App\Services;

use App\Models\BusinessServicePrice;
use App\Models\CategoryChildServiceFee;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingEngine
{
    public function prepare(int $businessId, int $serviceId): array
    {
        $service = PlatformService::query()
            ->findOrFail($serviceId);

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

        $feeRow = $this->resolveChildServiceFeeRow(
            childId: $childId,
            serviceId: $serviceId
        );

        $feeSnapshot = $this->buildFeeSnapshot(
            feeRow: $feeRow,
            businessId: $businessId,
            childId: $childId,
            serviceId: $serviceId
        );

        return [
            'service' => $service,
            'business' => $business,

            'business_child_id' => $childId,
            'child_id' => $childId,

            'business_price' => $businessPrice,
            'price' => $price,

            /*
            |--------------------------------------------------------------------------
            | Legacy / PlatformService Fee
            |--------------------------------------------------------------------------
            | هذا كان موجودًا قبل فصل رسوم البزنس والعميل.
            | نبقيه مؤقتًا للتوافق مع أي واجهات أو meta قديمة.
            */
            'platform_fee' => $platformFee,

            /*
            |--------------------------------------------------------------------------
            | Deposit Snapshot
            |--------------------------------------------------------------------------
            */
            'deposit' => $deposit,

            /*
            |--------------------------------------------------------------------------
            | New CategoryChild Service Fee Snapshot
            |--------------------------------------------------------------------------
            | المصدر الأساسي الجديد لرسوم التنفيذ:
            | category_child_service_fees
            */
            'service_fee_row' => $feeRow,
            'service_fee_rows' => [
                'business' => $feeSnapshot['business'],
                'client' => $feeSnapshot['client'],
            ],
            'fee_snapshot' => $feeSnapshot,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Business Context
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Fees
    |--------------------------------------------------------------------------
    */

    protected function resolveChildServiceFeeRow(int $childId, int $serviceId): ?CategoryChildServiceFee
    {
        if ($childId <= 0 || $serviceId <= 0) {
            return null;
        }

        return CategoryChildServiceFee::query()
            ->forPair($childId, $serviceId)
            ->active(1)
            ->first();
    }

    protected function buildFeeSnapshot(
        ?CategoryChildServiceFee $feeRow,
        int $businessId,
        int $childId,
        int $serviceId
    ): array {
        return [
            'business_id' => $businessId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'platform_service_id' => $serviceId,
            'fee_code' => WalletFeeService::DEFAULT_FEE_CODE,

            'business' => $feeRow
                ? $feeRow->toFeeSnapshot(CategoryChildServiceFee::PAYER_BUSINESS)
                : null,

            'client' => $feeRow
                ? $feeRow->toFeeSnapshot(CategoryChildServiceFee::PAYER_CLIENT)
                : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Legacy Platform Fee
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    */

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