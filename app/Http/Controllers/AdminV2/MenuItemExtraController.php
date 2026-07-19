<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use Illuminate\Http\Request;

class MenuItemExtraController extends Controller
{
    public function store(Request $request, MenuItem $menuItem)
    {
        $data = $this->validateData($request);
        $data['menu_item_id'] = $menuItem->id;

        MenuItemExtra::create($data);

        return back()->with('success', __('تمت إضافة الإضافة بنجاح.'));
    }

    public function update(Request $request, MenuItem $menuItem, MenuItemExtra $extra)
    {
        abort_unless((int) $extra->menu_item_id === (int) $menuItem->id, 404);

        $extra->update($this->validateData($request));

        return back()->with('success', __('تم تحديث الإضافة بنجاح.'));
    }

    public function destroy(MenuItem $menuItem, MenuItemExtra $extra)
    {
        abort_unless((int) $extra->menu_item_id === (int) $menuItem->id, 404);

        $extra->delete();

        return back()->with('success', __('تم حذف الإضافة بنجاح.'));
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'group_key' => ['nullable', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
        ], [], [
            'name_ar' => __('الاسم بالعربي'),
            'price' => __('السعر'),
        ]);

        $data['is_active'] = (bool) $request->boolean('is_active', true);

        return $data;
    }
}
