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
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        $items = MenuItem::query()
            ->where('business_id', $business)
            ->where('is_active', true)
            ->with([
                'activeVariants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id'),
                'activeExtras' => fn ($q) => $q->orderBy('group_key')->orderBy('id'),
                'offeringOptions.option',
                'section',
                'images',
            ])
            ->orderByDesc('is_featured')
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
                    'name' => $this->label($section->name_ar, $section->name_en, __('قسم #') . $section->id),
                    'source' => 'section',
                    'option_ids' => [],
                    'items' => $group->map(fn ($i) => $this->itemPayload($i))->values(),
                ];
            }
        }

        // Anything the merchant did not file by hand still gets a heading, from
        // the option combination he ticked at registration — «غرفة نوم —
        // مودرن», «مشويات» — falling back to the item type only for a child
        // with no line options yet. Only what has neither lands in «أخرى», and
        // that bucket exists so nothing is ever hidden, not as the default.
        $activeSectionIds = $sections->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ungrouped = $items->filter(function (MenuItem $i) use ($activeSectionIds) {
            $sid = (int) ($i->menu_section_id ?? 0);
            return $sid === 0 || ! in_array($sid, $activeSectionIds, true);
        });

        foreach ($this->headingsOf($ungrouped) as $heading) {
            $out[] = $heading;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                    'logo' => $biz->logo,
                    // So the order screen can show "closed now" and gate ordering.
                    'is_open_now' => app(\App\Services\BusinessHoursService::class)->isOpenNow((int) $biz->id),
                ],
                'sections' => $out,
            ],
        ]);
    }

    /**
     * Group items that carry no hand-written section under their taxonomy
     * heading, keeping the order the items already came in so a featured item
     * still pulls its heading up the page.
     *
     * @param  \Illuminate\Support\Collection<int,MenuItem>  $items
     * @return array<int,array<string,mixed>>
     */
    private function headingsOf($items): array
    {
        $groups = [];
        $loose = [];

        foreach ($items as $item) {
            $heading = $item->heading();

            if (! $heading) {
                $loose[] = $item;
                continue;
            }

            $groups[$heading['key']] ??= [
                'id' => null,
                'name' => $heading['label'],
                'source' => $heading['source'],
                // The exact filter that reproduces this heading, so tapping it
                // in the app is one call and not a guess.
                'option_ids' => $heading['option_ids'],
                'items' => [],
            ];

            $groups[$heading['key']]['items'][] = $this->itemPayload($item);
        }

        $out = array_values($groups);

        if (! empty($loose)) {
            $out[] = [
                'id' => null,
                'name' => __('أخرى'),
                'source' => 'none',
                'option_ids' => [],
                'items' => array_map(fn (MenuItem $i) => $this->itemPayload($i), $loose),
            ];
        }

        return $out;
    }

    private function itemPayload(MenuItem $item): array
    {
        $base = (float) $item->base_price;

        return [
            'id' => (int) $item->id,
            'name' => $this->label($item->name_ar, $item->name_en, __('صنف #') . $item->id),
            'description' => $this->label($item->description_ar, $item->description_en, ''),
            // «غرفة نوم — مودرن»: what the item is in the platform's own words,
            // so the option a customer searched by still shows on the result
            'offering_label' => $item->offeringLabel() ?: null,
            'option_ids' => $item->offeringOptions->pluck('option_id')->map(fn ($id) => (int) $id)->values(),
            // The legacy single column stays for whatever already reads it;
            // `images` is the gallery, and the one to draw.
            'image' => $item->image,
            'images' => $item->images->map(fn ($i) => ['id' => (int) $i->id, 'image' => $i->image])->values(),
            'base_price' => $base,
            // What the price is the price OF. Null is «by the item» — a
            // sandwich — and only a shop that weighs what it sells says
            // anything. The label is sent beside the code so the app prints
            // «٤٥ ج / كجم» without carrying its own unit table.
            'sale_unit' => $item->sale_unit ?: null,
            'sale_unit_label' => $item->priceUnitLabel(),
            /*
             * null = «لا أتابع الكمية», which is every kitchen and every row
             * written before 2026-08-24. 0 = «معروض، ونفد». The app must tell
             * them apart: the first says nothing, the second greys the row and
             * keeps the price on it — which is the whole reason this is not
             * done by switching the item off.
             */
            'available_quantity' => $item->available_quantity === null
                ? null
                : (int) $item->available_quantity,
            'variants' => $item->activeVariants->map(fn ($v) => [
                'id' => (int) $v->id,
                'name' => $this->label($v->name_ar, $v->name_en, __('حجم #') . $v->id),
                'type' => (string) $v->type,
                'price' => $v->resolvePrice($base),
                'is_default' => (bool) $v->is_default,
            ])->values(),
            'extras' => $item->activeExtras->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => $this->label($e->name_ar, $e->name_en, __('إضافة #') . $e->id),
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

        // Locale-first, then the other language, then the fallback — so an
        // English customer sees English names and Arabic fills any gaps.
        $primary   = app()->getLocale() === 'en' ? $en : $ar;
        $secondary = app()->getLocale() === 'en' ? $ar : $en;

        return $primary !== '' ? $primary : ($secondary !== '' ? $secondary : (string) $fallback);
    }
}
