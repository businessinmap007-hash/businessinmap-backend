<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Owner-panel management of a menu item's extras (add-ons: إضافة جبنة، صوص …).
 * Scoped: the item must belong to the logged-in business.
 */
class MenuItemExtraController extends Controller
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
        MenuItemExtra::create($this->validateData($request) + ['menu_item_id' => $item->id]);

        return back()->with('success', 'تمت إضافة الإضافة بنجاح.');
    }

    public function update(Request $request, int $menuItem, int $extra): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        $row = MenuItemExtra::query()->where('menu_item_id', $item->id)->findOrFail($extra);
        $row->update($this->validateData($request));

        return back()->with('success', 'تم تحديث الإضافة بنجاح.');
    }

    public function destroy(int $menuItem, int $extra): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        MenuItemExtra::query()->where('menu_item_id', $item->id)->findOrFail($extra)->delete();

        return back()->with('success', 'تم حذف الإضافة بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'group_key' => ['nullable', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable'],
        ], [], ['name_ar' => 'اسم الإضافة', 'price' => 'السعر']);

        return [
            'group_key' => trim((string) ($data['group_key'] ?? '')) ?: null,
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'price' => round((float) $data['price'], 2),
            'max_qty' => (int) ($data['max_qty'] ?? 1),
            'is_active' => (bool) $request->boolean('is_active', true),
        ];
    }
}
