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
        $rows = Medicine::query()
            ->search((string) $request->get('q', ''))
            ->limit(20)
            ->get(['id', 'name', 'strength', 'uses_count']);

        return response()->json([
            'success' => true,
            'total' => DB::table('medicines')->count(),
            'data' => $rows,
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
