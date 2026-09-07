<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemExtraGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Owner-panel management of a menu item's extra GROUPS — «الصوص»، «الإضافات»
 * — the thing that decides whether its extras render as radio (single) or
 * checkbox (multiple) on the customer side.
 */
class MenuItemExtraGroupController extends Controller
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
        MenuItemExtraGroup::create($this->validateData($request) + ['menu_item_id' => $item->id]);

        return back()->with('success', 'تمت إضافة المجموعة بنجاح.');
    }

    public function update(Request $request, int $menuItem, int $group): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        $row = MenuItemExtraGroup::query()->where('menu_item_id', $item->id)->findOrFail($group);
        $row->update($this->validateData($request));

        return back()->with('success', 'تم تحديث المجموعة بنجاح.');
    }

    /** Its extras fall back to standalone (nullOnDelete), not deleted. */
    public function destroy(int $menuItem, int $group): RedirectResponse
    {
        $item = $this->ownItem($menuItem);
        MenuItemExtraGroup::query()->where('menu_item_id', $item->id)->findOrFail($group)->delete();

        return back()->with('success', 'تم حذف المجموعة بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'selection_type' => ['required', Rule::in(MenuItemExtraGroup::SELECTION_TYPES)],
            'is_active' => ['nullable'],
        ], [], ['name_ar' => 'اسم المجموعة']);

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'selection_type' => $data['selection_type'],
            'is_active' => (bool) $request->boolean('is_active', true),
        ];
    }
}
