<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Tables" for the business owner (BIM-13.3) — each restaurant table gets a
 * permanent QR sticker; scanning it opens/joins that table's dine-in shared
 * cart. Simple scoped CRUD + a printable QR sheet. All queries scoped to
 * business_id = auth id.
 */
class TableController extends Controller
{
    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scoped(int $id): BusinessTable
    {
        return BusinessTable::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(): View
    {
        $rows = BusinessTable::query()
            ->where('business_id', $this->businessId())
            ->orderBy('id')
            ->paginate(60);

        return view('business.tables.index', ['rows' => $rows]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ], [], ['label' => 'اسم الطاولة']);

        BusinessTable::create([
            'business_id' => $this->businessId(),
            'label' => trim((string) $data['label']),
            'token' => BusinessTable::newToken(),
            'is_active' => 1,
        ]);

        return back()->with('success', 'تمت إضافة الطاولة بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable'],
        ], [], ['label' => 'اسم الطاولة']);

        $this->scoped($id)->update([
            'label' => trim((string) $data['label']),
            'is_active' => (int) $request->boolean('is_active'),
        ]);

        return back()->with('success', 'تم تحديث الطاولة بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        // orders.business_table_id is nullOnDelete — any placed order keeps its
        // history, just unlinked from the deleted table.
        $this->scoped($id)->delete();

        return redirect()->route('business.tables.index')->with('success', 'تم حذف الطاولة بنجاح.');
    }

    /** Printable QR sheet for all of the business's active tables. */
    public function print(): View
    {
        $rows = BusinessTable::query()
            ->where('business_id', $this->businessId())
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        return view('business.tables.print', ['rows' => $rows]);
    }
}
