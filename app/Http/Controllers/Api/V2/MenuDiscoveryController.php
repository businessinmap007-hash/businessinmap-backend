<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;

/**
 * Customer-facing browse of a single business's menu — the menu counterpart of
 * RetailDiscoveryController. Returns the active menu grouped by sections, each
 * item carrying its price, variants (sizes) and extras (add-ons), so the client
 * can render the menu and let the customer pick. Adding to the cart stays on the
 * authenticated cart endpoints (CartController).
 *
 * Public (no auth) — browsing a menu should not require signing in.
 */
final class MenuDiscoveryController extends Controller
{
    /** GET /api/v2/discovery/menu/{business} */
    public function show(int $business)
    {
        $biz = User::query()->where('type', 'business')->find($business, ['id', 'name', 'logo']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => 'النشاط غير موجود.'], 404);
        }

        $items = MenuItem::query()
            ->where('business_id', $business)
            ->where('is_active', true)
            ->with([
                'activeVariants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id'),
                'activeExtras' => fn ($q) => $q->orderBy('group_key')->orderBy('id'),
            ])
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get();

        $sections = MenuSection::query()
            ->where('business_id', $business)
            ->where('is_active', true)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        $bySection = $items->groupBy(fn (MenuItem $i) => (int) ($i->menu_section_id ?? 0));

        $out = [];

        foreach ($sections as $section) {
            $group = $bySection->get((int) $section->id);
            if ($group && $group->isNotEmpty()) {
                $out[] = [
                    'id' => (int) $section->id,
                    'name' => $this->label($section->name_ar, $section->name_en, 'قسم #' . $section->id),
                    'items' => $group->map(fn ($i) => $this->itemPayload($i))->values(),
                ];
            }
        }

        // Items with no (or an inactive) section fall into an "أخرى" bucket.
        $activeSectionIds = $sections->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ungrouped = $items->filter(function (MenuItem $i) use ($activeSectionIds) {
            $sid = (int) ($i->menu_section_id ?? 0);
            return $sid === 0 || ! in_array($sid, $activeSectionIds, true);
        });

        if ($ungrouped->isNotEmpty()) {
            $out[] = [
                'id' => null,
                'name' => 'أخرى',
                'items' => $ungrouped->map(fn ($i) => $this->itemPayload($i))->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                    'logo' => $biz->logo,
                ],
                'sections' => $out,
            ],
        ]);
    }

    private function itemPayload(MenuItem $item): array
    {
        $base = (float) $item->base_price;

        return [
            'id' => (int) $item->id,
            'name' => $this->label($item->name_ar, $item->name_en, 'صنف #' . $item->id),
            'description' => $this->label($item->description_ar, $item->description_en, ''),
            'image' => $item->image,
            'base_price' => $base,
            'variants' => $item->activeVariants->map(fn ($v) => [
                'id' => (int) $v->id,
                'name' => $this->label($v->name_ar, $v->name_en, 'حجم #' . $v->id),
                'type' => (string) $v->type,
                'price' => $v->resolvePrice($base),
                'is_default' => (bool) $v->is_default,
            ])->values(),
            'extras' => $item->activeExtras->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => $this->label($e->name_ar, $e->name_en, 'إضافة #' . $e->id),
                'group_key' => $e->group_key,
                'price' => (float) $e->price,
                'max_qty' => (int) ($e->max_qty ?: 1),
            ])->values(),
        ];
    }

    private function label($ar, $en, $fallback): string
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        return $ar !== '' ? $ar : ($en !== '' ? $en : (string) $fallback);
    }
}
