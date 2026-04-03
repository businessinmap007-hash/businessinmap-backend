<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        $rows = OptionGroup::query()
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
            'rows' => $rows,
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
        $group = new OptionGroup([
            'reorder' => 0,
            'is_active' => 1,
        ]);

        $selectedOptionIds = collect();

        $availableOptions = Option::query()
            ->with(['group:id,name_ar,name_en'])
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'group_id']);

        return view('admin-v2.options.groups.create', [
            'group' => $group,
            'availableOptions' => $availableOptions,
            'selectedOptionIds' => $selectedOptionIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['nullable', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],
        ]);

        $group = OptionGroup::query()->create([
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'reorder' => (int) ($data['reorder'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ]);

        $optionIds = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!empty($optionIds)) {
            Option::query()
                ->whereIn('id', $optionIds)
                ->update([
                    'group_id' => $group->id,
                ]);
        }

        return redirect()
            ->route('admin.option-groups.index')
            ->with('success', 'تمت إضافة مجموعة الخيارات بنجاح.');
    }

    public function edit(OptionGroup $optionGroup): View
    {
        $optionGroup->load([
            'options:id,name_ar,name_en,group_id',
        ]);

        $selectedOptionIds = $optionGroup->options
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $availableOptions = Option::query()
            ->with(['group:id,name_ar,name_en'])
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'group_id']);

        return view('admin-v2.options.groups.edit', [
            'group' => $optionGroup,
            'availableOptions' => $availableOptions,
            'selectedOptionIds' => $selectedOptionIds,
        ]);
    }

    public function update(Request $request, OptionGroup $optionGroup): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['nullable', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],
        ]);

        $optionGroup->update([
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'reorder' => (int) ($data['reorder'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 0),
        ]);

        $newOptionIds = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        Option::query()
            ->where('group_id', $optionGroup->id)
            ->when(!empty($newOptionIds), fn ($q) => $q->whereNotIn('id', $newOptionIds))
            ->update([
                'group_id' => null,
            ]);

        if (!empty($newOptionIds)) {
            Option::query()
                ->whereIn('id', $newOptionIds)
                ->update([
                    'group_id' => $optionGroup->id,
                ]);
        }

        return redirect()
            ->route('admin.option-groups.edit', $optionGroup->id)
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

    protected function hasIsActiveColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $hasColumn = Schema::hasColumn('options', 'is_active');

        return $hasColumn;
    }
}