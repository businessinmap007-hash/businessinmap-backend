<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Menu\MenuSheet;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * «استيراد وتصدير المنيو» — the merchant's menu as a sheet: download it (or an empty template in his own
 * vocabulary), fill it in Excel, send it back. See {@see MenuSheet}.
 */
final class BusinessMenuSheetController extends Controller
{
    public function __construct(private readonly MenuSheet $sheet)
    {
    }

    /** GET /api/v2/business/menu/sheet — the columns, the merchant's items as rows, and what «النوع»/«الوحدة» may say. */
    public function show(Request $request)
    {
        $business = BusinessContext::business($request);
        $template = $request->boolean('template');

        return response()->json(['success' => true, 'data' => [
            'columns' => $this->sheet->columns(),
            'rows' => $template ? $this->sheet->exampleRows($business) : $this->sheet->export($business),
            'vocabulary' => $this->sheet->vocabulary($business),
        ]]);
    }

    /** GET /api/v2/business/menu/sheet.csv — the same, as a CSV file Excel opens in Arabic. */
    public function csv(Request $request)
    {
        $business = BusinessContext::business($request);
        $template = $request->boolean('template');
        $rows = $template ? $this->sheet->exampleRows($business) : $this->sheet->export($business);
        $name = ($template ? 'menu-template-' : 'menu-') . $business->id . '.csv';

        return response($this->sheet->toCsv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /**
     * POST /api/v2/business/menu/inspect — `file` (CSV) or `grid` (an Excel file read on the phone, header row first):
     * the file's numbered headers, a few rows and the suggested mapping, so the merchant can point each of our
     * columns at the right column of HIS file.
     */
    public function inspect(Request $request)
    {
        $request->validate(['file' => ['nullable', 'file', 'max:5120'], 'grid' => ['nullable', 'array']]);

        $grid = $this->sheet->gridFromRequest($request);
        if ($grid === []) {
            return response()->json(['success' => false, 'message' => __('الملف فارغ أو بلا صفوف مقروءة.')], 422);
        }

        return response()->json(['success' => true, 'data' => $this->sheet->inspect($grid)]);
    }

    /**
     * POST /api/v2/business/menu/import — `file` (CSV), `grid` (an Excel file read on the phone, header row first)
     * or `rows` (already keyed by our columns), `mapping` (our column key => the file's column number; without it the
     * headers are recognised by name) and `dry_run` (default true: a preview that changes nothing).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:5120'],
            'rows' => ['nullable', 'array'],
            'grid' => ['nullable', 'array'],
            'mapping' => ['nullable'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $rows = $request->hasFile('file') || $request->has('grid')
            ? $this->sheet->rowsFromGrid($this->sheet->gridFromRequest($request), $this->sheet->mappingFromRequest($request))
            : (array) $request->input('rows', []);

        if ($rows === []) {
            return response()->json(['success' => false, 'message' => __('الملف فارغ أو بلا صفوف مقروءة.')], 422);
        }

        $report = $this->sheet->import($request, BusinessContext::business($request), $rows, $request->boolean('dry_run', true));

        return response()->json(['success' => true, 'data' => $report + ['dry_run' => $request->boolean('dry_run', true)]]);
    }
}
