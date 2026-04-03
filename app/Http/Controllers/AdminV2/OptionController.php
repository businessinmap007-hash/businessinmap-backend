<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OptionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $groupId = (string) $request->get('group_id', '');

        $query = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q2) => $q2->where('is_active', 1))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->when($groupId !== '', function ($q2) use ($groupId) {
                if ($groupId === 'ungrouped') {
                    $q2->whereNull('group_id');
                } else {
                    $q2->where('group_id', (int) $groupId);
                }
            })
            ->orderBy('id', 'desc');

        $rows = $query->paginate(50)->withQueryString();

        $groups = OptionGroup::query()
            ->orderBy('reorder')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        return view('admin-v2.options.index', [
            'rows' => $rows,
            'groups' => $groups,
            'q' => $q,
            'groupId' => $groupId,
        ]);
    }

    public function bulkAssignGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer', 'exists:options,id'],
            'target_group_id' => ['nullable', 'integer', 'exists:option_groups,id'],
        ]);

        $optionIds = collect($data['option_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $targetGroupId = !empty($data['target_group_id']) ? (int) $data['target_group_id'] : null;

        DB::transaction(function () use ($optionIds, $targetGroupId) {
            Option::query()
                ->whereIn('id', $optionIds)
                ->update([
                    'group_id' => $targetGroupId,
                ]);
        });

        return redirect()
            ->route('admin.options.index')
            ->with(
                'success',
                $targetGroupId
                    ? 'تم نقل الخيارات المحددة إلى المجموعة بنجاح.'
                    : 'تم فك ربط الخيارات المحددة من أي مجموعة.'
            );
    }

    public function create(): View
    {
        $row = new Option();

        return view('admin-v2.options.create', [
            'row' => $row,
            'hasIsActive' => $this->hasIsActiveColumn(),
            'hasSortOrder' => $this->hasSortOrderColumn(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('options', 'name_en')->where(function ($query) {
                    return $query->whereNotNull('name_en')->where('name_en', '!=', '');
                }),
            ],
        ];

        if ($this->hasIsActiveColumn()) {
            $rules['is_active'] = ['nullable', 'in:0,1'];
        }

        if ($this->hasSortOrderColumn()) {
            $rules['sort_order'] = ['nullable', 'integer', 'min:0', 'max:999999'];
        }

        $data = $request->validate($rules);

        $data['name_ar'] = trim((string) ($data['name_ar'] ?? ''));
        $data['name_en'] = trim((string) ($data['name_en'] ?? ''));

        if ($data['name_en'] === '') {
            $data['name_en'] = null;
        }

        if ($this->hasIsActiveColumn()) {
            $data['is_active'] = (int) ($data['is_active'] ?? 1);
        }

        if ($this->hasSortOrderColumn()) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }

        Option::create($data);

        return redirect()
            ->route('admin.options.index')
            ->with('success', 'تم إضافة الخيار بنجاح.');
    }

    public function edit(Option $option): View
    {
        return view('admin-v2.options.edit', [
            'row' => $option,
            'hasIsActive' => $this->hasIsActiveColumn(),
            'hasSortOrder' => $this->hasSortOrderColumn(),
        ]);
    }

    public function update(Request $request, Option $option): RedirectResponse
    {
        $rules = [
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('options', 'name_en')
                    ->ignore($option->id)
                    ->where(function ($query) {
                        return $query->whereNotNull('name_en')->where('name_en', '!=', '');
                    }),
            ],
        ];

        if ($this->hasIsActiveColumn()) {
            $rules['is_active'] = ['nullable', 'in:0,1'];
        }

        if ($this->hasSortOrderColumn()) {
            $rules['sort_order'] = ['nullable', 'integer', 'min:0', 'max:999999'];
        }

        $data = $request->validate($rules);

        $data['name_ar'] = trim((string) ($data['name_ar'] ?? ''));
        $data['name_en'] = trim((string) ($data['name_en'] ?? ''));

        if ($data['name_en'] === '') {
            $data['name_en'] = null;
        }

        if ($this->hasIsActiveColumn()) {
            $data['is_active'] = (int) ($data['is_active'] ?? $option->is_active ?? 1);
        }

        if ($this->hasSortOrderColumn()) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? $option->sort_order ?? 0);
        }

        $option->update($data);

        return redirect()
            ->route('admin.options.index')
            ->with('success', 'تم تحديث الخيار بنجاح.');
    }

    public function destroy(Option $option): RedirectResponse
    {
        $option->delete();

        return redirect()
            ->route('admin.options.index')
            ->with('success', 'تم حذف الخيار بنجاح.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer', 'exists:options,id'],
        ]);

        $optionIds = collect($data['option_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        Option::query()
            ->whereIn('id', $optionIds)
            ->delete();

        return redirect()
            ->route('admin.options.index')
            ->with('success', 'تم حذف الخيارات المحددة بنجاح.');
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

    protected function hasSortOrderColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $hasColumn = Schema::hasColumn('options', 'sort_order');

        return $hasColumn;
    }
}