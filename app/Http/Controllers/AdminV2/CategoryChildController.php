<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryChildController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function redirectToIndex(?int $parentId = null): RedirectResponse
    {
        $parentId = (int) ($parentId ?? 0);

        return $parentId > 0
            ? redirect()->route('admin.category-children.index', ['parent_id' => $parentId])
            : redirect()->route('admin.category-children.index');
    }

    private function normalizeNameEn(?string $value): string
    {
        return trim((string) $value);
    }

    private function normalizeNameAr(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function syncParents(CategoryChild $child, array $parentIds): void
    {
        $parentIds = collect($parentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $validParentIds = Category::query()
            ->where('parent_id', 0)
            ->whereIn('id', $parentIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $child->parents()->sync($validParentIds);
    }

    public function index(Request $request): View
    {
        $parentId = (int) $request->get('parent_id', 0);
        $q        = trim((string) $request->get('q', ''));
        $perPage  = $this->normalizePerPage($request->get('per_page', 50));
        $sort     = (string) $request->get('sort', 'name_en');
        $dir      = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'name_ar', 'name_en'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name_en';
        }

        $parents = Category::query()
            ->withoutGlobalScopes()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id', 'asc')
            ->get(['id', 'name_ar', 'name_en']);

        $parent = null;
        if ($parentId > 0) {
            $parent = Category::query()
                ->withoutGlobalScopes()
                ->where('parent_id', 0)
                ->find($parentId, ['id', 'name_ar', 'name_en']);
        }

        $rows = CategoryChild::query()
            ->with(['parents:id,name_ar,name_en'])
            ->withCount('parents')
            ->when($parentId > 0, function ($query) use ($parentId) {
                $query->whereHas('parents', function ($q) use ($parentId) {
                    $q->where('categories.id', $parentId);
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        $perPageOptions = self::PER_PAGE_ALLOWED;

        return view('admin-v2.category-children.index', [
            'rows' => $rows,
            'parents' => $parents,
            'parentId' => $parentId,
            'parent' => $parent,
            'q' => $q,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View
    {
        $row = new CategoryChild();

        $defaultParentId = (int) $request->get('parent_id', 0);

        $parents = Category::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->where('parent_id', 0)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        return view('admin-v2.category-children.create', [
            'row' => $row,
            'parents' => $parents,
            'selectedParentIds' => $defaultParentId > 0 ? [$defaultParentId] : [],
            'backUrl' => route('admin.category-children.index', $defaultParentId > 0 ? ['parent_id' => $defaultParentId] : []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => 'nullable|string|max:191',
            'name_en' => 'required|string|max:191',
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer|min:1',
        ]);

        $nameEn = $this->normalizeNameEn($data['name_en'] ?? null);
        $nameAr = $this->normalizeNameAr($data['name_ar'] ?? null);
        $parentIds = $data['parent_ids'] ?? [];

        if ($nameEn === '') {
            return back()
                ->withErrors(['name_en' => 'الاسم الإنجليزي مطلوب.'])
                ->withInput();
        }

        DB::transaction(function () use ($nameEn, $nameAr, $parentIds) {
            $child = CategoryChild::query()->firstOrCreate(
                ['name_en' => $nameEn],
                ['name_ar' => $nameAr]
            );

            if (! $child->name_ar && $nameAr) {
                $child->update(['name_ar' => $nameAr]);
            }

            $this->syncParents($child, $parentIds);
        });

        $firstParentId = collect($parentIds)->map(fn ($id) => (int) $id)->first(fn ($id) => $id > 0);

        return $this->redirectToIndex($firstParentId)
            ->with('success', 'تم إضافة القسم الفرعي الموحّد بنجاح');
    }

    public function edit(CategoryChild $categoryChild): View
    {
        $row = $categoryChild->load([
            'parents:id,name_ar,name_en',
        ]);

        $parents = Category::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->where('parent_id', 0)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $selectedParentIds = $row->parents
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('admin-v2.category-children.edit', [
            'row' => $row,
            'parents' => $parents,
            'selectedParentIds' => $selectedParentIds,
            'backUrl' => route('admin.category-children.index'),
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => 'nullable|string|max:191',
            'name_en' => 'required|string|max:191|unique:category_children_master,name_en,' . $categoryChild->id,
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer|min:1',
        ]);

        $nameEn = $this->normalizeNameEn($data['name_en'] ?? null);
        $nameAr = $this->normalizeNameAr($data['name_ar'] ?? null);
        $parentIds = $data['parent_ids'] ?? [];

        if ($nameEn === '') {
            return back()
                ->withErrors(['name_en' => 'الاسم الإنجليزي مطلوب.'])
                ->withInput();
        }

        DB::transaction(function () use ($categoryChild, $nameEn, $nameAr, $parentIds) {
            $categoryChild->update([
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
            ]);

            $this->syncParents($categoryChild, $parentIds);
        });

        $firstParentId = collect($parentIds)->map(fn ($id) => (int) $id)->first(fn ($id) => $id > 0);

        return $this->redirectToIndex($firstParentId)
            ->with('success', 'تم تحديث القسم الفرعي الموحّد بنجاح');
    }

    public function destroy(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $parentId = (int) $request->get('parent_id', 0);

        DB::transaction(function () use ($categoryChild) {
            $categoryChild->parents()->detach();
            $categoryChild->delete();
        });

        return $this->redirectToIndex($parentId)
            ->with('success', 'تم حذف القسم الفرعي الموحّد بنجاح');
    }

    public function detachParent(Request $request, CategoryChild $categoryChild, Category $parent): RedirectResponse
    {
        if ((int) $parent->parent_id !== 0) {
            return back()->withErrors([
                'error' => 'الربط يتم فقط مع الأقسام الرئيسية.',
            ]);
        }

        $categoryChild->parents()->detach($parent->id);

        return $this->redirectToIndex($parent->id)
            ->with('success', 'تم فصل الربط بين القسم الرئيسي والقسم الفرعي بنجاح');
    }
}