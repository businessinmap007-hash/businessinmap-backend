<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MenuBundle;
use App\Models\MenuBundleItem;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use App\Support\MarketCatalogChildren;

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
    private ?\App\Services\Menu\MenuItemAttributes $itemAttributes = null;

    /** GET /api/v2/discovery/menu/{business} */
    public function show(int $business)
    {
        $this->describingMemo = []; // a controller instance outlives one request under tests and long-lived workers
        $biz = User::query()->where('type', 'business')
            ->find($business, ['id', 'name', 'logo', 'category_child_id', 'category_id']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        // Worked out once per business rather than once per item — the same
        // config `heading()` would otherwise re-read on every row.
        $isGoodsCatalog = MarketCatalogChildren::includes($biz);
        $splitBranchesByGroup = $this->splitBranchesByGroup((int) ($biz->category_child_id ?? 0));

        $items = MenuItem::query()
            ->where('business_id', $business)
            ->where('is_active', true)
            ->with([
                'activeVariants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id'),
                'activeExtras' => fn ($q) => $q->orderBy('extra_group_id')->orderBy('id'),
                'activeExtraGroups',
                'offeringOptions.option.group',
                'section',
                'images',
                'catalogProduct:id,brand_id,series,main_image,main_image_credit',
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

        // One query pair for every unit's own values instead of one per item.
        ($this->itemAttributes ??= app(\App\Services\Menu\MenuItemAttributes::class))->preload($items->pluck('id')->all());

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

        foreach ($this->headingsOf($ungrouped, $isGoodsCatalog, $splitBranchesByGroup) as $heading) {
            $out[] = $heading;
        }

        // Fixed-composition combos ("وجبة العيلة") — rendered as their own
        // heading at the very top so the customer sees them before the raw
        // menu, same reuse-the-section-shape approach as everywhere else in
        // this response: one more "items" array, no new client-side widget.
        $bundles = MenuBundle::query()
            ->where('business_id', $business)
            ->where('is_active', true)
            ->with('items.menuItem')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get();

        if ($bundles->isNotEmpty()) {
            array_unshift($out, [
                'id' => null,
                'name' => __('باقات مجمّعة'),
                'source' => 'bundle',
                'option_ids' => [],
                'items' => $bundles->map(fn (MenuBundle $b) => $this->bundlePayload($b))->values(),
            ]);
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
                    // 'list' (default) or 'grid' — the merchant's own choice
                    // for how their menu renders; see BusinessMenuSetting.
                    'menu_display_mode' => \App\Models\BusinessMenuSetting::query()
                        ->where('business_id', $biz->id)
                        ->value('display_mode') ?: \App\Models\BusinessMenuSetting::DISPLAY_LIST,
                ],
                'sections' => $out,
                // What the store promises, answered once in its profile — returns, minimum order…
                'terms' => app(\App\Services\Menu\StoreTerms::class)->forCustomer((int) $biz->id),
            ],
        ]);
    }

    /**
     * GET /api/v2/discovery/menu-items/{item} — ONE item, shaped exactly like a
     * row of the shop's menu, so a search result can open the product page
     * itself instead of dropping the customer at the shop's front door.
     */
    public function item(int $item)
    {
        $this->describingMemo = [];
        $row = MenuItem::query()
            ->where('is_active', true)
            ->with([
                'activeVariants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id'),
                'activeExtras' => fn ($q) => $q->orderBy('extra_group_id')->orderBy('id'),
                'activeExtraGroups',
                'offeringOptions.option.group',
                'section',
                'images',
                'catalogProduct:id,brand_id,series,main_image,main_image_credit',
            ])
            ->find($item);

        $biz = $row
            ? User::query()->where('type', 'business')->find($row->business_id, ['id', 'name', 'logo'])
            : null;

        if (! $row || ! $biz) {
            return response()->json(['success' => false, 'message' => __('الصنف غير موجود.')], 404);
        }

        ($this->itemAttributes ??= app(\App\Services\Menu\MenuItemAttributes::class))->preload([(int) $row->id]);

        return response()->json([
            'success' => true,
            'data' => [
                'item' => $this->itemPayload($row),
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => (string) $biz->name,
                    'logo' => $biz->logo,
                    'is_open_now' => app(\App\Services\BusinessHoursService::class)->isOpenNow((int) $biz->id),
                ],
            ],
        ]);
    }

    /**
     * Group items that carry no hand-written section under their taxonomy
     * heading, keeping the order the items already came in so a featured item
     * still pulls its heading up the page.
     *
     * @param  \Illuminate\Support\Collection<int,MenuItem>  $items
     * @param  array<int,bool>  $splitBranchesByGroup
     * @return array<int,array<string,mixed>>
     */
    private function headingsOf($items, bool $isGoodsCatalog, array $splitBranchesByGroup = []): array
    {
        $groups = [];
        $loose = [];

        foreach ($items as $item) {
            $heading = $item->heading($isGoodsCatalog, $splitBranchesByGroup);

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

    /**
     * A bundle's card in the menu: its own name, its computed price, and a
     * description built from the component list ("برجر × 1، بطاطس × 1،
     * مشروب × 1") so what's inside is visible without any new UI — the
     * client already renders `description` under the name for a plain item.
     */
    private function bundlePayload(MenuBundle $bundle): array
    {
        $contents = $bundle->items->map(function (MenuBundleItem $i) {
            $item = $i->menuItem;
            $name = $item ? $this->label($item->name_ar, $item->name_en, '#' . $i->menu_item_id) : ('#' . $i->menu_item_id);

            return $i->qty > 1 ? ($name . ' × ' . $i->qty) : $name;
        })->values()->all();

        return [
            'id' => (int) $bundle->id,
            'kind' => 'bundle',
            'name' => $this->label($bundle->name_ar, $bundle->name_en, __('باقة #') . $bundle->id),
            'description' => implode('، ', $contents),
            'offering_label' => null,
            'option_ids' => [],
            'image' => null,
            'images' => [],
            'base_price' => $bundle->price(),
            'sale_unit' => null,
            'sale_unit_label' => null,
            'brand_name' => null,
            'available_quantity' => null,
            'is_featured' => false,
            'variants' => [],
            'extra_groups' => [],
            'extras' => [],
        ];
    }

    /** @var array<int, list<string>> detail kind id => attribute codes on its card, in order */
    private array $cardCodes = [];

    /**
     * Where the item's own description shows, per the detail kind's «الوصف» field in
     * «أشكال المنيو»: on the CARD only when the kind ticked it for the card; on the PAGE
     * when the kind ticked it for the page. A kind that dropped the field shows the
     * description nowhere (the add-product box goes with it); a basic menu has no
     * kind, so it keeps the page and never the card.
     *
     * @return array{card:bool,page:bool}
     */
    private function descriptionPlaces(MenuItem $item): array
    {
        $profileId = (int) ($item->lineOption()?->group?->menu_detail_profile_id ?? 0);
        if ($profileId <= 0) {
            return ['card' => false, 'page' => true];
        }

        return $this->describingMemo['desc' . $profileId] ??= (function () use ($profileId) {
            $field = collect(\App\Models\MenuDetailProfile::fieldsFor([$profileId])[$profileId] ?? [])->firstWhere('code', 'description');

            return $field ? ['card' => (bool) $field['show_on_card'], 'page' => (bool) $field['show_on_page']] : ['card' => false, 'page' => false];
        })();
    }

    /** @param  list<array{code:string,name:string,value:string}>  $specs */
    private function cardSummary(MenuItem $item, array $specs): ?string
    {
        $profileId = (int) ($item->lineOption()?->group?->menu_detail_profile_id ?? 0);
        if ($profileId <= 0) {
            return null;
        }

        $this->cardCodes[$profileId] ??= collect(\App\Models\MenuDetailProfile::fieldsFor([$profileId])[$profileId] ?? [])
            ->where('show_on_card', true)->pluck('code')->all();

        $byCode = collect($specs)->keyBy('code');
        if ($this->descriptionPlaces($item)['card'] && ($text = $this->label($item->description_ar, $item->description_en, '')) !== '') {
            $byCode['description'] = ['code' => 'description', 'value' => \Illuminate\Support\Str::limit($text, 90)];
        }
        $line = collect($this->cardCodes[$profileId])
            ->map(fn ($code) => $byCode[$code]['value'] ?? null)
            ->filter()->implode(' · ');

        return $line !== '' ? $line : null;
    }

    /** @var array<string,mixed> memo for describingSpecs(): kind → chosen groups, child → descriptive groups */
    private array $describingMemo = [];

    /**
     * The item's choices from its DESCRIBING groups as spec rows — name = the
     * group, value = what was chosen. Which groups count: the ones the admin
     * ticked for the item's kind in «أشكال المنيو» (in that order); a kind that
     * never chose shows every group «مكونات الخدمة» made descriptive for the
     * business's own trade.
     *
     * @return list<array{code:string,name:string,value:string}>
     */
    private function describingSpecs(MenuItem $item): array
    {
        $modifiers = $item->modifierOptions()->filter(fn ($o) => $o->group);
        if ($modifiers->isEmpty()) {
            return [];
        }

        // «مكونات الخدمة» decides, per trade: which groups describe an item, in
        // what order, and whether the customer's product page shows them.
        $childId = (int) ($this->describingMemo['c' . $item->business_id] ??= (int) User::query()->whereKey($item->business_id)->value('category_child_id'));
        $allowed = $this->describingMemo['d' . $childId] ??= $this->descriptiveGroupIdsFor($childId);

        $rows = [];
        foreach ($allowed as $groupId) {
            $chosen = $modifiers->filter(fn ($o) => (int) $o->group_id === (int) $groupId);
            if ($chosen->isEmpty()) {
                continue;
            }
            $rows[] = [
                'code' => 'group_' . (int) $groupId,
                'name' => (string) $chosen->first()->group->displayName(),
                'value' => $chosen->map(fn ($o) => (string) $o->displayName())->implode('، '),
            ];
        }

        return $rows;
    }

    /**
     * Drops the spec rows the admin turned off for the product page («في صفحة
     * المنتج» in «أشكال المنيو») — catalog fields and the unit's own values alike.
     *
     * @param  list<array<string,mixed>>  $specs
     * @return list<array<string,mixed>>
     */
    private function withoutHiddenSpecs(MenuItem $item, array $specs): array
    {
        $profileId = (int) ($item->lineOption()?->group?->menu_detail_profile_id ?? 0);
        if ($profileId <= 0 || $specs === []) {
            return $specs;
        }

        $hidden = $this->describingMemo['h' . $profileId] ??= collect(\App\Models\MenuDetailProfile::fieldsFor([$profileId])[$profileId] ?? [])
            ->where('show_on_page', false)->pluck('code')->all();

        return $hidden === [] ? $specs : array_values(array_filter($specs, fn ($row) => ! in_array($row['code'] ?? '', $hidden, true)));
    }

    /** @return list<int> the groups «مكونات الخدمة» made descriptive for this child under the menu service, shown on the product page, in order */
    private function descriptiveGroupIdsFor(int $childId): array
    {
        $menuServiceId = (int) \App\Models\PlatformService::query()->where('key', \App\Models\PlatformService::KEY_MENU)->value('id');

        if ($childId <= 0 || $menuServiceId <= 0) {
            return [];
        }

        return app(\App\Services\Catalog\ServiceOptionPlacements::class)
            ->for($menuServiceId, $childId, \App\Models\ServiceOptionGroupPlacement::ITEM_DESCRIBING)
            ->where('show_on_page', true)
            ->pluck('option_group_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function itemPayload(MenuItem $item): array
    {
        $base = (float) $item->base_price;
        $specs = $item->catalog_product_id
            ? app(\App\Services\Catalog\ProductSpecs::class)->forProducts([(int) $item->catalog_product_id])[(int) $item->catalog_product_id] ?? []
            : [];
        // The unit's own values (a car's year and mileage) over its master's.
        $specs = ($this->itemAttributes ??= app(\App\Services\Menu\MenuItemAttributes::class))->mergeIntoSpecs($specs, (int) $item->id);
        // …and what the merchant chose from the groups that DESCRIBE it («طراز
        // الأثاث: مودرن»، «أنواع الأخشاب: زان») — the descriptive fields picked in
        // «أشكال المنيو» — so the customer's product page lists them with the rest.
        $specs = array_merge($specs, $this->describingSpecs($item));
        $specs = $this->withoutHiddenSpecs($item, $specs);

        return [
            'id' => (int) $item->id,
            'kind' => 'menu',
            'name' => $this->label($item->name_ar, $item->name_en, __('صنف #') . $item->id),
            'description' => $this->descriptionPlaces($item)['page'] ? $this->label($item->description_ar, $item->description_en, '') : '',
            // «غرفة نوم — مودرن»: what the item is in the platform's own words,
            // so the option a customer searched by still shows on the result
            'offering_label' => $item->offeringLabel() ?: null,
            'option_ids' => $item->offeringOptions->pluck('option_id')->map(fn ($id) => (int) $id)->values(),
            // «جديد»/«مستعمل», when the merchant qualified this item with
            // «حالة المنتج» — the badge TechProductDetail shows beside the
            // price. Matched by group name the same way `is_condition` is
            // server-side in BusinessMenuItemController::vocabulary() —
            // never guessed from a hardcoded option id.
            'condition' => ($condition = $item->modifierOptions()->first(
                fn ($o) => $o->group && str_contains((string) $o->group->name_ar, 'حالة المنتج')
            )) ? [
                'id' => (int) $condition->id,
                'name' => $this->label($condition->name_ar, $condition->name_en, ''),
            ] : null,
            // The branch this item sits under within its section — e.g.
            // "ثلاجات" inside "أنواع الأجهزة الكهربائية" — so the app can
            // group a section's items by branch and let a customer jump to
            // one directly, the way it already jumps to a section.
            'line_option' => ($lineOption = $item->lineOption()) ? [
                'id' => (int) $lineOption->id,
                'name_ar' => $lineOption->name_ar,
                'name_en' => $lineOption->name_en,
            ] : null,
            // The legacy single column stays for whatever already reads it;
            // `images` is the gallery, and the one to draw.
            // The merchant's own photo first; a device he did not photograph
            // shows its catalog master's open-licensed image instead.
            'image' => $item->image ?: ($item->catalogProduct?->main_image ?: null),
            // The licence credit that catalog photo must carry (CC BY-SA…);
            // null when the merchant's own photo is shown.
            'image_credit' => $item->image ? null : ($item->catalogProduct?->main_image_credit ?: null),
            // `source: camera` = a live shot (required for a second-hand
            // unit) — the app puts the camera badge on it.
            'images' => $item->images->map(fn ($i) => ['id' => (int) $i->id, 'image' => $i->image, 'source' => $i->source])->values(),
            'base_price' => $base,
            // What the price is the price OF. Null is «by the item» — a
            // sandwich — and only a shop that weighs what it sells says
            // anything. The label is sent beside the code so the app prints
            // «٤٥ ج / كجم» without carrying its own unit table.
            'sale_unit' => $item->sale_unit ?: null,
            'sale_unit_label' => $item->priceUnitLabel(),
            // «كيلو وربع ونص»: may a part of the unit be ordered?
            'fractional' => \App\Support\SaleUnits::isFractional($item->sale_unit),
            // The maker, when the merchant said one — «هل يوجد اسم الشركة
            // المنتجة او الماركة». Never `supply_price`: that is what the
            // merchant paid, and this endpoint is the public one.
            'brand_name' => $item->brand_name ?: null,
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
            // Doubles as the customer-facing "Bestseller" badge — the same
            // flag already used to sort featured items first, above.
            'is_featured' => (bool) $item->is_featured,
            // Only present when the merchant linked a real catalog master
            // (a real phone/laptop model…) — see [[three-catalog-shapes]].
            'specs' => $specs,
            // The one line under the name on a card — «٢٠٢١ · ٤٢٬٥٠٠ كم ·
            // أوتوماتيك · بنزين» — from the fields its detail kind puts on the
            // card (set in «أشكال المنيو»), in that order. Null under a basic
            // menu; the app then falls back to the first few specs.
            'card_summary' => $this->cardSummary($item, $specs),
            /*
             * The two filter facets of a catalog-linked device — «سامسونج» →
             * «Galaxy A». The storefront groups a section's items by these so
             * a customer narrows أوبو → F instead of scrolling. Null for an
             * item with no catalog master (a sandwich has no series).
             */
            'catalog_brand_id' => $item->catalogProduct?->brand_id ? (int) $item->catalogProduct->brand_id : null,
            'catalog_brand' => collect($specs)->firstWhere('code', 'brand')['value'] ?? null,
            'series' => $item->catalogProduct?->series ?: null,
            // «كاش أو أقساط»: the plans a customer may pick on the line (none = cash only).
            'payment_plans' => ($plans = app(\App\Services\Menu\PaymentPlans::class)->present($item, $base)),
            // The ONE line a card says about instalments («تقسيط من … شهريًا على … شهر»); null = cash only.
            'installment' => app(\App\Services\Menu\PaymentPlans::class)->cardLine($plans),
            'variants' => $item->activeVariants->map(fn ($v) => [
                'id' => (int) $v->id,
                'name' => $this->label($v->name_ar, $v->name_en, __('حجم #') . $v->id),
                'type' => (string) $v->type,
                'price' => $v->resolvePrice($base),
                'is_default' => (bool) $v->is_default,
                // An instalment option says over how many months it runs.
                'installment_months' => $v->installment_months !== null ? (int) $v->installment_months : null,
                'installment_down' => $v->installment_down !== null ? (float) $v->installment_down : null,
            ])->values(),
            // Groups first (each with its own selection_type — 'single' means
            // the client must render a radio, not a checkbox, and enforce
            // exactly one pick before "add to cart" makes sense) so the
            // client never has to guess grouping/type from the flat list.
            'extra_groups' => $item->activeExtraGroups->map(fn ($g) => [
                'id' => (int) $g->id,
                'name' => $this->label($g->name_ar, $g->name_en, __('مجموعة #') . $g->id),
                'selection_type' => $g->selection_type,
            ])->values(),
            'extras' => $item->activeExtras->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => $this->label($e->name_ar, $e->name_en, __('إضافة #') . $e->id),
                'extra_group_id' => $e->extra_group_id !== null ? (int) $e->extra_group_id : null,
                'price' => (float) $e->price,
                'max_qty' => (int) ($e->max_qty ?: 1),
            ])->values(),
        ];
    }

    /**
     * option_group_id => true for every group this child's «مكونات الخدمة»
     * (menu service) turned `branches_as_sections` on for — see
     * {@see MenuItem::heading()} and [[tech-spec-menu-implementation]].
     *
     * @return array<int,bool>
     */
    private function splitBranchesByGroup(int $childId): array
    {
        if ($childId <= 0) {
            return [];
        }

        $menuServiceId = (int) \App\Models\PlatformService::query()
            ->where('key', \App\Models\PlatformService::KEY_MENU)->value('id');

        if ($menuServiceId <= 0) {
            return [];
        }

        return app(\App\Services\Catalog\ServiceOptionPlacements::class)
            ->for($menuServiceId, $childId, \App\Models\ServiceOptionGroupPlacement::USAGE_SECTION)
            ->filter(fn ($p) => $p->branches_as_sections)
            ->mapWithKeys(fn ($p) => [(int) $p->option_group_id => true])
            ->all();
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
