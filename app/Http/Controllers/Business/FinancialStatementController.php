<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessFinancialLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * كشف الوارد والصادر والمكسب — قراءةٌ مباشرة لصفوف {@see BusinessFinancialLedger}
 * المتراكمة أصلًا (`FinancialLedgerService`)؛ لا تجميع هنا مطلقًا.
 */
class FinancialStatementController extends Controller
{
    public function index(): View
    {
        $businessId = (int) Auth::id();

        $rows = BusinessFinancialLedger::query()
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('source');

        $total = $rows->get(BusinessFinancialLedger::SOURCE_TOTAL);

        $bySource = collect(BusinessFinancialLedger::SOURCES)
            ->mapWithKeys(fn ($source) => [$source => $rows->get($source)])
            ->filter();

        return view('business.financial-statement.index', [
            'total' => $total,
            'bySource' => $bySource,
            'labels' => [
                BusinessFinancialLedger::SOURCE_MENU => __('المنيو'),
                BusinessFinancialLedger::SOURCE_RETAIL => __('التجزئة'),
                BusinessFinancialLedger::SOURCE_BOOKING => __('الحجوزات'),
            ],
        ]);
    }
}
