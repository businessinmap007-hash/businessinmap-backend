<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $businessId = (int) $request->get('business_id', 0);
        $isActive = $request->get('is_active', '');

        $businesses = $this->businesses();

        $rows = MenuItem::query()
            ->with(['business:id,name,type'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->when($businessId > 0, fn ($query) => $query->where('business_id', $businessId))
            ->when($isActive !== '' && $isActive !== null, fn ($query) => $query->where('is_active', (int) $isActive))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.menu-items.index', compact('rows', 'businesses', 'q', 'businessId', 'isActive'));
    }

    public function create(Request $request)
    {
        $row = new MenuItem([
            'base_price' => 0,
            'sort_order' => 0,
            'is_active' => 1,
            'business_id' => (int) $request->get('business_id', 0) ?: null,
        ]);

        return view('admin-v2.menu-items.create', [
            'row' => $row,
            'businesses' => $this->businesses(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $row = MenuItem::create($data);

        return redirect()
            ->route('admin.menu-items.edit', $row)
            ->with('success', 'تم إنشاء عنصر المنيو بنجاح.');
    }

    public function edit(MenuItem $menuItem)
    {
        $menuItem->load(['business:id,name,type', 'variants', 'extras']);

        return view('admin-v2.menu-items.edit', [
            'row' => $menuItem,
            'businesses' => $this->businesses(),
        ]);
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $data = $this->validateData($request);
        $menuItem->update($data);

        return back()->with('success', 'تم تحديث عنصر المنيو بنجاح.');
    }

    public function destroy(MenuItem $menuItem)
    {
        $menuItem->delete();

        return redirect()
            ->route('admin.menu-items.index')
            ->with('success', 'تم حذف عنصر المنيو بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'business'))],
            'category_id' => ['nullable', 'integer'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:191'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable'],
        ], [], [
            'business_id' => 'البزنس',
            'name_ar' => 'الاسم بالعربي',
            'base_price' => 'السعر الأساسي',
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    protected function businesses()
    {
        return User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
