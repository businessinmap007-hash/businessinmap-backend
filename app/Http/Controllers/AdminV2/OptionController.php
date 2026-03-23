<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;

class OptionController extends Controller
{
    
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $groupFilter = (string) $request->get('group_id', '');

        $groups = OptionGroup::query()
            ->withCount('options')
            ->with(['options' => function ($query) use ($q, $groupFilter) {
                $query
                    ->when($q !== '', function ($sub) use ($q) {
                        $sub->where(function ($w) use ($q) {
                            $w->where('name_ar', 'like', "%{$q}%")
                            ->orWhere('name_en', 'like', "%{$q}%");
                        });
                    })
                    ->when($this->hasIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                    ->orderBy('id', 'asc');
            }])
            ->when($groupFilter !== '' && $groupFilter !== 'ungrouped', function ($query) use ($groupFilter) {
                $query->where('id', (int) $groupFilter);
            })
            ->orderBy('reorder')
            ->orderBy('id')
            ->get();

        $ungroupedOptions = Option::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->when($this->hasIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
            ->whereNull('group_id')
            ->orderBy('id', 'asc')
            ->get(['id', 'group_id', 'name_ar', 'name_en']);

        $allGroups = OptionGroup::query()
            ->orderBy('reorder')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        $ungroupedCount = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
            ->whereNull('group_id')
            ->count();

        $groupedCount = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
            ->whereNotNull('group_id')
            ->count();

        $totalCount = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
            ->count();

        return view('admin-v2.options.index', [
            'groups' => $groups,
            'allGroups' => $allGroups,
            'ungroupedOptions' => $ungroupedOptions,
            'ungroupedCount' => $ungroupedCount,
            'groupedCount' => $groupedCount,
            'totalCount' => $totalCount,
            'q' => $q,
            'groupFilter' => $groupFilter,
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
            ->with('success', $targetGroupId
                ? 'تم نقل الخيارات المحددة إلى المجموعة بنجاح.'
                : 'تم فك ربط الخيارات المحددة من أي مجموعة.');
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
            'name_en' => ['nullable', 'string', 'max:191'],
        ];

        if ($this->hasIsActiveColumn()) {
            $rules['is_active'] = ['nullable', 'in:0,1'];
        }

        if ($this->hasSortOrderColumn()) {
            $rules['sort_order'] = ['nullable', 'integer', 'min:0', 'max:999999'];
        }

        $data = $request->validate($rules);

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
            'name_en' => ['nullable', 'string', 'max:191'],
        ];

        if ($this->hasIsActiveColumn()) {
            $rules['is_active'] = ['nullable', 'in:0,1'];
        }

        if ($this->hasSortOrderColumn()) {
            $rules['sort_order'] = ['nullable', 'integer', 'min:0', 'max:999999'];
        }

        $data = $request->validate($rules);

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

 

    protected function hasSortOrderColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $hasColumn = Schema::hasColumn('options', 'sort_order');

        return $hasColumn;
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
   
}