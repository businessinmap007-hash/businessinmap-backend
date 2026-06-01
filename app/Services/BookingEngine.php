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
            ->select([
                'id',
                'key',
                'name_ar',
                'name_en',
                'is_active',
                'supports_deposit',
            ])
            ->findOrFail($serviceId);

        [$business, $childId] = $this->resolveBusinessContext($businessId);

        if (! $business) {
            throw ValidationException::withMessages([
                'business_id' => 'البزنس غير موجود أو غير صحيح.',
            ]);
        }

        if (! (bool) $service->is_active) {
            throw ValidationException::withMessages([
                'service_id' => 'هذه الخدمة غير مفعلة حاليًا.',
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

        $price = $this->resolvePrice($businessPrice);

        $feeRow = $this->resolveChildServiceFeeRow(
            childId: $childId,
            serviceId: $serviceId
        );

        $feeSnapshot = $this->buildFeeSnapshot(
            feeRow: $feeRow,
            businessId: $businessId,
            childId: $childId,
            serviceId: $serviceId,
            baseAmount: $price
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
            | Platform Fee
            |--------------------------------------------------------------------------
            | لا يتم حساب أي رسوم من platform_services.
            | المصدر الصحيح لرسوم العميل والبزنس هو:
            | category_child_service_fees
            | والعروض أعلى أولوية داخل WalletFeeService.
            |--------------------------------------------------------------------------
            */
            'platform_fee' => 0.00,

            /*
            |--------------------------------------------------------------------------
            | Deposit
            |--------------------------------------------------------------------------
            | لا يتم حساب الديبوزت من platform_services أو business_service_prices
            | في هذه المرحلة. مصدره النهائي يجب أن يكون category_child_service_fees
            | أو من logic مخصص مرتبط بها لاحقًا.
            |--------------------------------------------------------------------------
            */
            'deposit' => 0.00,

            /*
            |--------------------------------------------------------------------------
            | CategoryChild Service Fee Snapshot
            |--------------------------------------------------------------------------
            | المصدر الأساسي لرسوم التنفيذ:
            | category_child_service_fees
            |--------------------------------------------------------------------------
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
            ->select(['id', 'type', 'category_id', 'category_child_id'])
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

    protected function resolvePrice(BusinessServicePrice $businessPrice): float
    {
        $price = $businessPrice->price ?? null;

        if ($price === null || (float) $price <= 0) {
            $price = $businessPrice->base_price ?? 0;
        }

        return round(max((float) $price, 0), 2);
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

        return CategoryChildServiceFee::activeForPair($childId, $serviceId);
    }

    protected function buildFeeSnapshot(
        ?CategoryChildServiceFee $feeRow,
        int $businessId,
        int $childId,
        int $serviceId,
        float $baseAmount = 0
    ): array {
        $businessSnapshot = $feeRow
            ? $feeRow->toFeeSnapshot(CategoryChildServiceFee::PAYER_BUSINESS, $baseAmount)
            : null;

        $clientSnapshot = $feeRow
            ? $feeRow->toFeeSnapshot(CategoryChildServiceFee::PAYER_CLIENT, $baseAmount)
            : null;

        return [
            'business_id' => $businessId,
            'child_id' => $childId,
            'service_id' => $serviceId,
            'platform_service_id' => $serviceId,

            'fee_code' => CategoryChildServiceFee::DEFAULT_FEE_CODE,
            'fee_row_id' => $feeRow ? (int) $feeRow->id : null,
            'category_child_service_fee_id' => $feeRow ? (int) $feeRow->id : null,

            'business' => $businessSnapshot,
            'client' => $clientSnapshot,
        ];
    }
}