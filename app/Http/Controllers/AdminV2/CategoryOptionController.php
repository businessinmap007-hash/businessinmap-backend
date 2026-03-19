<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CategoryOptionController extends Controller
{
    public function edit(Request $request, Category $category): View
    {
        $qAvailable = trim((string) $request->get('q_available', ''));
        $qSelected = trim((string) $request->get('q_selected', ''));

        $assignedOptionIds = $category->categoryOptions()
            ->pluck('options.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereIn('id', !empty($assignedOptionIds) ? $assignedOptionIds : [0])
            ->when($qSelected !== '', function ($query) use ($qSelected) {
                $query->where(function ($w) use ($qSelected) {
                    $w->where('name_ar', 'like', "%{$qSelected}%")
                        ->orWhere('name_en', 'like', "%{$qSelected}%");
                });
            })
            ->ordered()
            ->get(['id', 'name_ar', 'name_en']);

        $availableOptions = Option::query()
            ->when($this->hasIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereNotIn('id', !empty($assignedOptionIds) ? $assignedOptionIds : [0])
            ->when($qAvailable !== '', function ($query) use ($qAvailable) {
                $query->where(function ($w) use ($qAvailable) {
                    $w->where('name_ar', 'like', "%{$qAvailable}%")
                        ->orWhere('name_en', 'like', "%{$qAvailable}%");
                });
            })
            ->ordered()
            ->get(['id', 'name_ar', 'name_en']);

        return view('admin-v2.categories.options', [
            'category' => $category,
            'availableOptions' => $availableOptions,
            'selectedOptions' => $selectedOptions,
            'qAvailable' => $qAvailable,
            'qSelected' => $qSelected,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],
        ]);

        $optionIds = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $category->categoryOptions()->sync($optionIds);

        return redirect()
            ->route('admin.categories.options.edit', $category)
            ->with('success', 'تم تحديث خيارات التصنيف بنجاح.');
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