<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MenuItemVariantController extends Controller
{
    public function store(Request $request, MenuItem $menuItem)
    {
        $data = $this->validateData($request);
        $data['menu_item_id'] = $menuItem->id;

        if ($data['is_default']) {
            $menuItem->variants()->update(['is_default' => false]);
        }

        MenuItemVariant::create($data);

        return back()->with('success', 'تمت إضافة variant بنجاح.');
    }

    public function update(Request $request, MenuItem $menuItem, MenuItemVariant $variant)
    {
        abort_unless((int) $variant->menu_item_id === (int) $menuItem->id, 404);

        $data = $this->validateData($request);

        if ($data['is_default']) {
            $menuItem->variants()->where('id', '!=', $variant->id)->update(['is_default' => false]);
        }

        $variant->update($data);

        return back()->with('success', 'تم تحديث الـ variant بنجاح.');
    }

    public function destroy(MenuItem $menuItem, MenuItemVariant $variant)
    {
        abort_unless((int) $variant->menu_item_id === (int) $menuItem->id, 404);

        $variant->delete();

        return back()->with('success', 'تم حذف الـ variant بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_delta' => ['nullable', 'numeric'],
            'is_default' => ['nullable'],
            'is_active' => ['nullable'],
        ], [], [
            'type' => 'النوع',
            'name_ar' => 'الاسم بالعربي',
        ]);

        if (($data['price'] ?? null) === '' || ($data['price'] ?? null) === null) {
            $data['price'] = null;
        }

        if (($data['price_delta'] ?? null) === '' || ($data['price_delta'] ?? null) === null) {
            $data['price_delta'] = null;
        }

        if ($data['price'] === null && $data['price_delta'] === null) {
            throw ValidationException::withMessages([
                'price' => 'أدخل سعرًا مباشرًا أو فرق سعر عن السعر الأساسي.',
            ]);
        }

        $data['is_default'] = (bool) $request->boolean('is_default');
        $data['is_active'] = (bool) $request->boolean('is_active', true);

        return $data;
    }
}
