<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "My menu" for the business owner. Simple scoped CRUD over menu_items; these
 * are the items customers can order (dine-in food attaches to a booking, or a
 * standalone menu order). Every query is scoped to business_id = auth id.
 */
class MenuItemController extends Controller
{
    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scopedItem(int $id): MenuItem
    {
        return MenuItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $active = $request->get('active', '');

        $rows = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->when($active !== '' && $active !== null, fn ($query) => $query->where('is_active', (int) $active))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(name_ar) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$term]);
                });
            })
            ->with('section:id,name_ar')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.menu.index', [
            'rows' => $rows,
            'q' => $q,
            'active' => (string) $active,
        ]);
    }

    public function create(): View
    {
        return view('business.menu.create', [
            'row' => new MenuItem(['is_active' => 1, 'sort_order' => 0, 'base_price' => 0]),
            'sections' => $this->sections(),
        ]);
    }

    /** The owner's sections for the item form dropdown. */
    private function sections()
    {
        return MenuSection::query()
            ->where('business_id', $this->businessId())
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar']);
    }

    public function store(Request $request): RedirectResponse
    {
        MenuItem::create($this->validateData($request) + ['business_id' => $this->businessId()]);

        return redirect()
            ->route('business.menu.index')
            ->with('success', 'تمت إضافة الصنف بنجاح.');
    }

    public function edit(int $id): View
    {
        $row = $this->scopedItem($id);
        $row->load(['variants' => fn ($q) => $q->orderBy('id'), 'extras' => fn ($q) => $q->orderBy('id')]);

        return view('business.menu.edit', [
            'row' => $row,
            'sections' => $this->sections(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->scopedItem($id)->update($this->validateData($request));

        return back()->with('success', 'تم تحديث الصنف بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scopedItem($id)->delete();

        return redirect()
            ->route('business.menu.index')
            ->with('success', 'تم حذف الصنف بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'menu_section_id' => [
                'nullable', 'integer',
                Rule::exists('menu_sections', 'id')->where('business_id', $this->businessId()),
            ],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ], [], [
            'name_ar' => 'الاسم العربي',
            'base_price' => 'السعر',
            'menu_section_id' => 'القسم',
        ]);

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'menu_section_id' => ($data['menu_section_id'] ?? null) ?: null,
            'description_ar' => trim((string) ($data['description_ar'] ?? '')) ?: null,
            'description_en' => trim((string) ($data['description_en'] ?? '')) ?: null,
            'base_price' => round((float) $data['base_price'], 2),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
