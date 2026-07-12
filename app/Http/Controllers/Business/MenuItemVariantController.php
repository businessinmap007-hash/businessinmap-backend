<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Owner-panel management of a menu item's variants (sizes / options). Mirrors
 * AdminV2\MenuItemVariantController but scoped: the item must belong to the
 * logged-in business.
 */
class MenuItemVariantController extends Controller
{
    private function ownItem(int $menuItemId): MenuItem
    {
        return MenuItem::query()
            ->where('business_id', (int) Auth::id())
            ->findOrFail($menuItemId);
    }

    public function store(Request $request, int $menuItem): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        $data = $this->validateData($request);
        $data['menu_item_id'] = $item->id;

        if ($data['is_default']) {
            $item->variants()->update(['is_default' => false]);
        }

        MenuItemVariant::create($data);

        return back()->with('success', 'تمت إضافة الحجم بنجاح.');
    }

    public function update(Request $request, int $menuItem, int $variant): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        $row = MenuItemVariant::query()->where('menu_item_id', $item->id)->findOrFail($variant);

        $data = $this->validateData($request);

        if ($data['is_default']) {
            $item->variants()->where('id', '!=', $row->id)->update(['is_default' => false]);
        }

        $row->update($data);

        return back()->with('success', 'تم تحديث الحجم بنجاح.');
    }

    public function destroy(int $menuItem, int $variant): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        MenuItemVariant::query()->where('menu_item_id', $item->id)->findOrFail($variant)->delete();

        return back()->with('success', 'تم حذف الحجم بنجاح.');
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
        ], [], ['type' => 'النوع', 'name_ar' => 'الاسم بالعربي']);

        $price = ($data['price'] ?? null) === '' ? null : $data['price'];
        $delta = ($data['price_delta'] ?? null) === '' ? null : $data['price_delta'];

        if ($price === null && $delta === null) {
            throw ValidationException::withMessages([
                'price' => 'أدخل سعرًا مباشرًا أو فرق سعر عن السعر الأساسي.',
            ]);
        }

        return [
            'type' => trim((string) $data['type']),
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'price' => $price !== null ? round((float) $price, 2) : null,
            'price_delta' => $delta !== null ? round((float) $delta, 2) : null,
            'is_default' => (bool) $request->boolean('is_default'),
            'is_active' => (bool) $request->boolean('is_active', true),
        ];
    }
}
