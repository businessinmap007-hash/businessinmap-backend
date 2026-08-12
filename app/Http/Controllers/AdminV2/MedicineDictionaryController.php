<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The shared drug dictionary a doctor types against, and the door to fill it.
 *
 * It was built to grow by itself — every drug written into a prescription is
 * remembered — which is right, and leaves the FIRST doctor typing into an empty
 * box. The register (an EDA export, a supplier catalogue, a hospital formulary)
 * comes in through here.
 *
 * The screen is a thin shell over `medicines:import`, so the console and the
 * panel can never diverge on what a valid row is. That parsing is the part
 * worth having exactly once.
 */
class MedicineDictionaryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = Medicine::query()
            ->search($q)
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.medicines.index', [
            'rows' => $rows,
            'q' => $q,
            'total' => DB::table('medicines')->count(),
            'prescribed' => DB::table('medicines')->where('uses_count', '>', 0)->count(),
            // What is left for a human: no stated strength and none readable
            // out of the name either.
            'missingStrength' => DB::table('medicines')
                ->whereNull('strength')->whereNull('strength_derived')->count(),
        ]);
    }

    /**
     * The doctor's typeahead, live, so it can be tried rather than described.
     *
     * It calls `Medicine::scopeSearch` — the same scope the app endpoint calls —
     * because a preview that runs its own query would eventually flatter a
     * search the doctor is not actually given. The app route sits behind
     * `auth:sanctum` and an admin session cannot reach it, which is the only
     * reason this second door exists.
     */
    public function search(Request $request)
    {
        $term = (string) $request->get('q', '');

        $rows = Medicine::query()
            ->search($term)
            ->limit(20)
            ->get(['id', 'name', 'strength', 'scientific_name', 'name_ar', 'manufacturer', 'price_egp', 'uses_count']);

        return response()->json([
            'success' => true,
            'total' => DB::table('medicines')->count(),
            'data' => $rows->map(fn (Medicine $m) => [
                'id' => (int) $m->id,
                'name' => $m->name,
                'scientific_name' => $m->scientific_name,
                'manufacturer' => $m->manufacturer,
                'price_egp' => $m->price_egp !== null ? (float) $m->price_egp : null,
                'uses_count' => (int) $m->uses_count,
                // A doctor who typed an ingredient and got twenty brand names
                // deserves to be told that is what happened, not left to guess.
                'matched_on' => $m->matchedOn($term),
            ])->values(),
        ]);
    }

    /** Upload a register and hand it to the same importer the console uses. */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,json', 'max:20480'],
        ], [], ['file' => 'الملف']);

        // Kept out of the public disk: a register is not something to serve.
        $path = $request->file('file')->store('imports');
        $full = storage_path('app/' . $path);

        $code = Artisan::call('medicines:import', [
            'file' => $full,
            '--dry-run' => $request->boolean('dry_run'),
        ]);

        @unlink($full);

        $output = trim(Artisan::output());

        return back()->with($code === 0 ? 'success' : 'error', $output !== '' ? $output : __('تعذّرت قراءة الملف.'));
    }

    /**
     * The whole dictionary as one sheet.
     *
     * 42% of the register states no strength anywhere — not in a column, not in
     * the name — and no parser invents one. Nor can a parser tell «AUGMENTIN 1
     * GM» from «A.ONE SOAP 100 GM». Both are jobs for someone who knows what
     * the product is, so the sheet goes out, gets edited, and comes back
     * through the importer — the `id` column is what lands each correction on
     * the row it was made for.
     */
    public function export(Request $request)
    {
        $only = $request->boolean('missing_strength');
        $name = 'medicines-' . ($only ? 'missing-strength-' : '') . now()->toDateString() . '.csv';

        return response()->streamDownload(function () use ($only) {
            $out = fopen('php://output', 'w');

            // Excel reads a CSV as the local codepage without this, and every
            // Arabic name arrives as mojibake. The importer strips it coming back.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, Medicine::SHEET_COLUMNS);

            Medicine::query()
                ->when($only, fn ($q) => $q->whereNull('strength')->whereNull('strength_derived'))
                ->orderBy('name')
                ->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, $row->toSheetRow());
                    }
                });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Remove one entry.
     *
     * Only an entry no doctor has written yet: `uses_count` above zero means it
     * is in someone's prescription history, and a typeahead that forgets a drug
     * a patient is holding a paper for helps nobody.
     */
    public function destroy(int $medicine)
    {
        $row = Medicine::query()->findOrFail($medicine);

        if ((int) $row->uses_count > 0) {
            return back()->with('error', __('هذا الدواء مكتوب في روشتات بالفعل — لا يُحذف.'));
        }

        $row->delete();

        return back()->with('success', __('تم حذف الدواء من القاموس.'));
    }
}
