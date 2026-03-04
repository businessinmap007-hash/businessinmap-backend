<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function redirectToIndex(?int $rootId = null)
    {
        $rootId = (int) ($rootId ?? 0);
        return $rootId > 0
            ? redirect()->route('admin.categories.index', ['root_id' => $rootId])
            : redirect()->route('admin.categories.index');
    }

    private function storeUploadedImage(Request $request, ?string $oldPath = null): ?string
    {
        // ✅ لو مفيش ملف مرفوع: ارجع القديم كما هو
        if (!$request->hasFile('image')) {
            return $oldPath;
        }

        $file = $request->file('image');

        // ✅ أمان إضافي
        if (!$file || !$file->isValid()) {
            return $oldPath;
        }

        $name = time() . '.' . $file->getClientOriginalExtension();

        // المسار اللي هيتخزن في DB
        $path = 'files/uploads/' . $name;

        // ارفع للـ public/files/uploads
        $file->move(public_path('files/uploads'), $name);

        // ✅ رجّع المسار الجديد
        return $path;
    }


    public function index(Request $request)
    {
        $rootId  = (int) $request->get('root_id', 0);
        $q       = trim((string) $request->get('q', ''));
        $active  = $request->get('active'); // '' | 1 | 0
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        // Sorting
        $sort = (string) $request->get('sort', 'reorder');  // reorder | name_ar | name_en | id
        $dir  = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['reorder', 'name_ar', 'name_en', 'id'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'reorder';
        }

        // Roots dropdown
        $roots = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id', 'asc')
            ->get(['id', 'name_ar', 'name_en', 'image', 'per_month', 'per_year']);

        // ✅ selected root info for the "card"
        $root = null;
        if ($rootId > 0) {
            $root = Category::query()
                ->where('parent_id', 0)
                ->find($rootId);
        }

        $children = null;

        if ($rootId > 0) {
            $children = Category::query()
                ->where('parent_id', $rootId)

                // Search
                ->when($q !== '', function ($qq) use ($q) {
                    $qq->where(function ($w) use ($q) {
                        $w->where('name_ar', 'like', "%{$q}%")
                          ->orWhere('name_en', 'like', "%{$q}%");
                    });
                })

                // Active filter
                ->when($active !== null && $active !== '', fn ($qq) => $qq->where('is_active', (int) $active))

                // Sorting
                ->when(true, function ($qq) use ($sort, $dir) {
                    if ($sort === 'reorder') {
                        $qq->orderByRaw('COALESCE(reorder, 999999) ' . $dir)
                           ->orderBy('id', 'asc');
                    } else {
                        $qq->orderBy($sort, $dir)
                           ->orderBy('id', 'asc');
                    }
                })

                ->paginate($perPage)
                ->withQueryString();
        }

        $activeOptions  = ['' => 'الكل', '1' => 'نشط', '0' => 'غير نشط'];
        $perPageOptions = self::PER_PAGE_ALLOWED;

        return view('admin-v2.categories.index', compact(
            'roots',
            'rootId',
            'root',      // ✅ important
            'children',
            'q',
            'active',
            'activeOptions',
            'perPage',
            'perPageOptions',
            'sort',
            'dir'
        ));
    }

    public function create(Request $request)
    {
        $defaultParentId = (int) $request->get('parent_id', 0);

        $parents = Category::query()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        return view('admin-v2.categories.create', compact('parents', 'defaultParentId'));
    }

    public function store(Request $request)
    {
        // ✅ supports upload
        $data = $request->validate([
            'name_ar'   => 'required|string|max:191',
            'name_en'   => 'nullable|string|max:191',
            'parent_id' => 'required|integer|min:0',
            'is_active' => 'nullable|in:0,1',
            'reorder'   => 'nullable|integer|min:0|max:1000000',
            'per_month' => 'nullable|numeric|min:0',
            'per_year'  => 'nullable|numeric|min:0',

            // ✅ file upload
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data['is_active'] = (int) ($data['is_active'] ?? 1);
        $data['parent_id'] = (int) ($data['parent_id'] ?? 0);

        // image upload
        $data['image'] = $this->storeUploadedImage($request, null);

        Category::create($data);

        // لو أضفت فرعي -> ارجع لنفس root
        $rootId = (int) $request->input('root_id', 0);

        return redirect()
            ->route('admin.categories.index', $rootId > 0 ? ['root_id' => $rootId] : [])
            ->with('success', 'تم إضافة القسم بنجاح');
    }

    public function edit(Request $request, Category $category)
    {
        // عشان نرجع لنفس root بعد الحفظ
        $rootId = (int) $request->get('root_id', $category->parent_id ?: 0);

        $parents = Category::query()
            ->where('parent_id', 0)
            ->where('id', '!=', $category->id)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        return view('admin-v2.categories.edit', compact('category', 'parents', 'rootId'));
    }

    public function update(Request $request, Category $category)
    {
        $rootId = (int) $request->get('root_id', $category->parent_id ?: 0);

        // ✅ supports upload
        $data = $request->validate([
            'name_ar'   => 'required|string|max:191',
            'name_en'   => 'nullable|string|max:191',
            'parent_id' => 'required|integer|min:0',
            'is_active' => 'nullable|in:0,1',
            'reorder'   => 'nullable|integer|min:0|max:1000000',
            'per_month' => 'nullable|numeric|min:0',
            'per_year'  => 'nullable|numeric|min:0',

            // ✅ file upload
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $pid = (int) ($data['parent_id'] ?? 0);

        if ($pid === (int) $category->id) {
            return back()->withErrors(['error' => 'لا يمكن جعل القسم تابعًا لنفسه.'])->withInput();
        }

        // ✅ basic protection: prevent moving root under its own child
        if ((int)$category->parent_id === 0 && $pid > 0) {
            $childIds = Category::query()->where('parent_id', $category->id)->pluck('id')->all();
            if (in_array($pid, $childIds, true)) {
                return back()->withErrors(['error' => 'لا يمكن جعل القسم الرئيسي تابعًا لأحد أقسامه الفرعية.'])->withInput();
            }
        }

        $data['is_active'] = (int) ($data['is_active'] ?? $category->is_active);
        $data['parent_id'] = $pid;

        // image upload (delete old if new)
        $data['image'] = $this->storeUploadedImage($request, $category->image);

        $category->update($data);

        // رجّع لنفس root
        return redirect()
            ->route('admin.categories.index', ($request->filled('root_id') && (int)$request->root_id > 0)
                ? ['root_id' => (int)$request->root_id]
                : []
            )
            ->with('success', 'تم تحديث القسم بنجاح');
    }

    public function destroy(Request $request, Category $category)
    {
        $rootId = (int) $request->get('root_id', $category->parent_id ?: 0);

        if ($category->children()->exists()) {
            return back()->withErrors(['error' => 'لا يمكن حذف قسم لديه أقسام فرعية. احذف الأقسام الفرعية أولاً.']);
        }

        // delete image
        if (!empty($category->image) && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return $this->redirectToIndex($rootId)
            ->with('success', 'تم حذف القسم بنجاح');
    }

        public function toggleActive(Category $category)
    {
        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'ok' => true,
            'id' => $category->id,
            'is_active' => (int)$category->is_active,
        ]);
    }


    public function updateReorder(Request $request, \App\Models\Category $category)
    {
        $data = $request->validate([
            'reorder' => 'required|integer|min:0|max:999999',
        ]);

        $category->reorder = (int) $data['reorder'];
        $category->save();

        return response()->json(['ok' => true, 'reorder' => $category->reorder]);
    }

   
}
