<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OptionController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $rows = Option::query()
            ->when($this->hasIsActiveColumn(), function ($query) use ($request) {
                $active = $request->get('active', '');

                if ($active !== '' && $active !== null) {
                    $query->where('is_active', (int) $active);
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->ordered()
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.options.index', [
            'rows' => $rows,
            'q' => $q,
            'active' => $request->get('active', ''),
            'hasIsActive' => $this->hasIsActiveColumn(),
            'hasSortOrder' => $this->hasSortOrderColumn(),
        ]);
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