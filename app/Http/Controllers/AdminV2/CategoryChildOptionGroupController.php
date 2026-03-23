<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildOptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryChildOptionGroupController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function backToIndex(CategoryChild $categoryChild, ?int $parentId = null): RedirectResponse
    {
        return redirect()->route('admin.category-child-option-groups.index', [
            'categoryChild' => $categoryChild->id,
            'parent_id' => (int) ($parentId ?? 0),
        ]);
    }

    public function index(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);
        $q = trim((string) $request->get('q', ''));
        $active = $request->get('active', '');
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $query = CategoryChildOptionGroup::query()
            ->withCount('childOptionLinks')
            ->where('child_id', $categoryChild->id);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name_ar', 'like', '%' . $q . '%')
                    ->orWhere('name_en', 'like', '%' . $q . '%');
            });
        }

        if ($active !== '' && $active !== null) {
            $query->where('is_active', (int) $active);
        }

        $groups = $query
            ->orderBy('reorder')
            ->orderBy('id')
            ->paginate($perPage)
            ->appends($request->query());

        $parent = null;
        if ($parentId > 0) {
            $parent = Category::query()->find($parentId);
        }

        return view('admin-v2.category-children.option-groups.index', [
            'categoryChild' => $categoryChild,
            'parentId' => $parentId,
            'parent' => $parent,
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

    public function create(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        return view('admin-v2.category-children.option-groups.create', [
            'categoryChild' => $categoryChild,
            'parentId' => $parentId,
            'group' => new CategoryChildOptionGroup([
                'is_active' => 1,
                'reorder' => 0,
            ]),
        ]);
    }

    public function store(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $parentId = (int) $request->input('parent_id', 0);

        $data = $request->validate([
            'name_ar'   => ['nullable', 'string', 'max:191'],
            'name_en'   => ['nullable', 'string', 'max:191'],
            'reorder'   => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        CategoryChildOptionGroup::create([
            'child_id'   => $categoryChild->id,
            'name_ar'    => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'name_en'    => trim((string) ($data['name_en'] ?? '')) ?: null,
            'reorder'    => (int) ($data['reorder'] ?? 0),
            'is_active'  => (int) ($data['is_active'] ?? 0),
        ]);

        return $this->backToIndex($categoryChild, $parentId)
            ->with('success', 'تمت إضافة مجموعة الخيارات بنجاح.');
    }

    public function edit(Request $request, CategoryChild $categoryChild, CategoryChildOptionGroup $group): View
    {
        abort_if((int) $group->child_id !== (int) $categoryChild->id, 404);

        return view('admin-v2.category-children.option-groups.edit', [
            'categoryChild' => $categoryChild,
            'parentId' => (int) $request->get('parent_id', 0),
            'group' => $group,
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild, CategoryChildOptionGroup $group): RedirectResponse
    {
        abort_if((int) $group->child_id !== (int) $categoryChild->id, 404);

        $parentId = (int) $request->input('parent_id', 0);

        $data = $request->validate([
            'name_ar'   => ['nullable', 'string', 'max:191'],
            'name_en'   => ['nullable', 'string', 'max:191'],
            'reorder'   => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $group->update([
            'name_ar'    => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'name_en'    => trim((string) ($data['name_en'] ?? '')) ?: null,
            'reorder'    => (int) ($data['reorder'] ?? 0),
            'is_active'  => (int) ($data['is_active'] ?? 0),
        ]);

        return $this->backToIndex($categoryChild, $parentId)
            ->with('success', 'تم تحديث مجموعة الخيارات بنجاح.');
    }

    public function destroy(Request $request, CategoryChild $categoryChild, CategoryChildOptionGroup $group): RedirectResponse
    {
        abort_if((int) $group->child_id !== (int) $categoryChild->id, 404);

        $parentId = (int) $request->input('parent_id', $request->get('parent_id', 0));

        $fallbackGroup = CategoryChildOptionGroup::query()
            ->where('child_id', $categoryChild->id)
            ->where('id', '!=', $group->id)
            ->orderBy('reorder')
            ->orderBy('id')
            ->first();

        if (! $fallbackGroup) {
            return $this->backToIndex($categoryChild, $parentId)
                ->withErrors(['error' => 'لا يمكن حذف آخر مجموعة. أنشئ مجموعة بديلة أولاً.']);
        }

        $group->childOptionLinks()->update([
            'group_id' => $fallbackGroup->id,
        ]);

        $group->delete();

        return $this->backToIndex($categoryChild, $parentId)
            ->with('success', 'تم حذف المجموعة ونقل الخيارات إلى مجموعة بديلة.');
    }
}