<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Services\Menu\MenuSheet;
use App\Support\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «استيراد وتصدير المنيو» on the web panel. The page reads an Excel or CSV file in the browser and sends its rows
 * (so the server needs no spreadsheet library), shows the preview, then imports on «تأكيد». Same service as the
 * app's door — {@see MenuSheet}.
 */
class MenuSheetController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly MenuSheet $sheet)
    {
    }

    public function index(): View
    {
        return view('business.menu.import');
    }

    /** The sheet as JSON — the page builds the Excel file from it. */
    public function show(Request $request): JsonResponse
    {
        $business = BusinessContext::business($request);
        $template = $request->boolean('template');

        return response()->json(['success' => true, 'data' => [
            'columns' => $this->sheet->columns(),
            'rows' => $template ? $this->sheet->exampleRows($business) : $this->sheet->export($business),
            'vocabulary' => $this->sheet->vocabulary($business),
        ]]);
    }

    public function csv(Request $request)
    {
        $business = BusinessContext::business($request);
        $template = $request->boolean('template');
        $rows = $template ? $this->sheet->exampleRows($business) : $this->sheet->export($business);

        return response($this->sheet->toCsv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . ($template ? 'menu-template' : 'menu') . '.csv"',
        ]);
    }

    /** The picked file's numbered headers, a few rows and the suggested mapping — the page then lets the owner correct it. */
    public function inspect(Request $request): JsonResponse
    {
        $request->validate(['grid' => ['required', 'array', 'min:1']]);

        return response()->json(['success' => true, 'data' => $this->sheet->inspect((array) $request->input('grid'))]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => ['nullable', 'array'],
            'grid' => ['nullable', 'array'],
            'mapping' => ['nullable', 'array'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $rows = $request->has('grid')
            ? $this->sheet->rowsFromGrid((array) $request->input('grid'), $this->sheet->mappingFromRequest($request))
            : (array) $request->input('rows', []);
        abort_if($rows === [], 422, __('الملف فارغ أو بلا صفوف مقروءة.'));

        $dryRun = $request->boolean('dry_run', true);
        $report = $this->sheet->import($request, BusinessContext::business($request), $rows, $dryRun);

        return response()->json(['success' => true, 'data' => $report + ['dry_run' => $dryRun]]);
    }
}
