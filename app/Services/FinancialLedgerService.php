<?php

namespace App\Services;

use App\Models\BusinessFinancialLedger;
use Illuminate\Support\Facades\DB;

/**
 * كشف الوارد والصادر والمكسب لكل نشاط — رصيدٌ متراكم، لا تقرير مُجمَّع.
 *
 * كل استدعاء يضيف على الصف القائم مباشرة (`lockForUpdate` + حفظ)، فالقراءة فى
 * أى لحظة صفٌّ واحد جاهز، مهما بلغ عدد الطلبات والحجوزات التاريخية — المالك:
 * «كل عملية صادر تجمع على السابقة مباشرة ويكون الناتج لحظى لا يحتاج اعادة
 * تجميع كل مرة». يُستدعى من نقاط إتمام البيع (`OrderHandoverService`،
 * `BookingController::complete`) ونقاط خصم رسوم المنصة
 * (`OrderFeeSettlementService`، `WalletFeeService`) — أربعة نداءات، لا تجميعًا
 * لاحقًا. كل نداء يحدّث صفَّ مصدره (menu|retail|booking) وصفَّ `total` معًا فى
 * نفس المعاملة، فيبقى الإجمالى دائمًا مطابقًا لمجموع مصادره.
 */
class FinancialLedgerService
{
    public function recordSale(int $businessId, string $source, float $revenue, float $costOfGoods): void
    {
        if ($businessId <= 0) {
            return;
        }

        $this->applyDelta($businessId, $source, $revenue, $costOfGoods, 0.0);
        $this->applyDelta($businessId, BusinessFinancialLedger::SOURCE_TOTAL, $revenue, $costOfGoods, 0.0);
    }

    public function recordFee(int $businessId, string $source, float $fee): void
    {
        if ($businessId <= 0 || $fee <= 0) {
            return;
        }

        $this->applyDelta($businessId, $source, 0.0, 0.0, $fee);
        $this->applyDelta($businessId, BusinessFinancialLedger::SOURCE_TOTAL, 0.0, 0.0, $fee);
    }

    private function applyDelta(int $businessId, string $source, float $revenue, float $cost, float $fee): void
    {
        DB::transaction(function () use ($businessId, $source, $revenue, $cost, $fee) {
            $row = BusinessFinancialLedger::query()
                ->where('business_id', $businessId)
                ->where('source', $source)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = new BusinessFinancialLedger([
                    'business_id' => $businessId,
                    'source' => $source,
                    'revenue_total' => 0,
                    'cost_of_goods_total' => 0,
                    'platform_fees_total' => 0,
                    'operations_count' => 0,
                ]);
            }

            $row->revenue_total = round((float) $row->revenue_total + $revenue, 2);
            $row->cost_of_goods_total = round((float) $row->cost_of_goods_total + $cost, 2);
            $row->platform_fees_total = round((float) $row->platform_fees_total + $fee, 2);
            $row->operations_count = (int) $row->operations_count + 1;
            $row->save();
        });
    }
}
