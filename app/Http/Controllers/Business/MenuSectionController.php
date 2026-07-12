<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\MenuSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Menu sections" for the business owner — named groups (مقبلات / رئيسي /
 * حلويات) that organise the menu. Simple scoped CRUD; every query is scoped to
 * business_id = auth id.
 */
class MenuSectionController extends Controller
{
    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scoped(int $id): MenuSection
    {
        return MenuSection::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(): View
    {
        $rows = MenuSection::query()
            ->where('business_id', $this->businessId())
            ->withCount('items')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->paginate(50);

        return view('business.menu-sections.index', ['rows' => $rows]);
    }

    public function create(): View
    {
        return view('business.menu-sections.create', [
            'row' => new MenuSection(['is_active' => 1, 'sort_order' => 0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MenuSection::create($this->validateData($request) + ['business_id' => $this->businessId()]);

        return redirect()->route('business.menu-sections.index')->with('success', 'تمت إضافة القسم بنجاح.');
    }

    public function edit(int $id): View
    {
        return view('business.menu-sections.edit', ['row' => $this->scoped($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->scoped($id)->update($this->validateData($request));

        return back()->with('success', 'تم تحديث القسم بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        // menu_items.menu_section_id nullOnDelete — items become ungrouped, not deleted.
        $this->scoped($id)->delete();

        return redirect()->route('business.menu-sections.index')->with('success', 'تم حذف القسم بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ], [], ['name_ar' => 'اسم القسم']);

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
