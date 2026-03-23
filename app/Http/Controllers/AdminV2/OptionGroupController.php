<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\OptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OptionGroupController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $active = (string) $request->get('active', '');
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $groups = OptionGroup::query()
            ->withCount('options')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                      ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->when($active !== '', function ($query) use ($active) {
                $query->where('is_active', (int) $active);
            })
            ->orderBy('reorder')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.options.groups.index', [
            'groups' => $groups,
            'q' => $q,
            'active' => $active,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_ALLOWED,
            'activeOptions' => [
                ''  => 'الكل',
                '1' => 'نشط',
                '0' => 'غير نشط',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin-v2.options.groups.create', [
            'group' => new OptionGroup([
                'reorder' => 0,
                'is_active' => 1,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar'   => ['nullable', 'string', 'max:191'],
            'name_en'   => ['nullable', 'string', 'max:191'],
            'reorder'   => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        OptionGroup::create([
            'name_ar'   => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'name_en'   => trim((string) ($data['name_en'] ?? '')) ?: null,
            'reorder'   => (int) ($data['reorder'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ]);

        return redirect()
            ->route('admin.option-groups.index')
            ->with('success', 'تمت إضافة مجموعة الخيارات بنجاح.');
    }

    public function edit(OptionGroup $optionGroup): View
    {
        return view('admin-v2.options.groups.edit', [
            'group' => $optionGroup,
        ]);
    }

    public function update(Request $request, OptionGroup $optionGroup): RedirectResponse
    {
        $data = $request->validate([
            'name_ar'   => ['nullable', 'string', 'max:191'],
            'name_en'   => ['nullable', 'string', 'max:191'],
            'reorder'   => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $optionGroup->update([
            'name_ar'   => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'name_en'   => trim((string) ($data['name_en'] ?? '')) ?: null,
            'reorder'   => (int) ($data['reorder'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ]);

        return redirect()
            ->route('admin.option-groups.index')
            ->with('success', 'تم تحديث مجموعة الخيارات بنجاح.');
    }

     public function destroy(OptionGroup $optionGroup): RedirectResponse
    {
        $optionGroup->options()->update([
            'group_id' => null,
        ]);

        $optionGroup->delete();

        return redirect()
            ->route('admin.option-groups.index')
            ->with('success', 'تم حذف مجموعة الخيارات وفك ربط الخيارات التابعة لها.');
    }
    
}