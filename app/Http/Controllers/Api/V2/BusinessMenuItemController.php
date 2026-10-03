<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\MenuItemResource;
use App\Models\BusinessMenuSetting;
use App\Models\Image;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemExtraGroup;
use App\Models\MenuItemVariant;
use App\Models\OptionGroup;
use App\Services\CategoryChildOptionScope;
use App\Services\Media\ImageUploadService;
use App\Services\Menu\MenuItemAttributes;
use App\Services\Menu\MenuSectionFromOptionGroup;
use App\Services\MerchantOfferingVocabulary;
use App\Support\SaleUnits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * v2 business menu management — the business role edits its own menu from the
 * app (mirrors the web Business\MenuItem/Variant/Extra controllers, which had
 * no API). Every row is scoped to business_id = the authenticated user.
 */
final class BusinessMenuItemController extends Controller
{
    use ResolvesOwnerCatalog;

    /** As many as a listing can usefully carry, and few enough to stay a page. */
    private const MAX_IMAGES = 10;

    public function __construct(
        private readonly MerchantOfferingVocabulary $vocabulary,
        private readonly MenuSectionFromOptionGroup $sectionFromGroup,
        private readonly CategoryChildOptionScope $childScope,
    ) {
    }

    /**
     * GET /api/v2/business/menu/vocabulary — what this merchant may say a
     * catalog item IS (`lines`, grouped by option group — the branches under
     * a section) and what may qualify it (`modifiers` — brand, condition...),
     * narrowed to this business's own ticks. Same source the web panel's
     * pricing screen already reads: {@see MerchantOfferingVocabulary}.
     */
    public function vocabulary(Request $request)
    {
        $vocabulary = $this->vocabulary->for($this->businessId($request), $this->childId(), $this->rootId());
        $descriptiveIds = $this->descriptiveGroupIds();
        $vocabulary['lines'] = $this->withoutDescriptiveGroups(collect($vocabulary['lines']), $descriptiveIds);
        $profiles = $this->detailProfilesFor(
            collect($vocabulary['lines'])->map(fn ($options) => (int) $options->first()->group_id)->values()->all()
        );

        // Grouping keys on the Arabic name (a stable, unique identifier
        // regardless of the request's own locale) — `is_brand` matches
        // against THAT, never the localized label chosen for display below.
        $businessId = $this->businessId($request);
        // «مكونات الخدمة» — the ONE place that decides how a descriptive field
        // behaves for this trade: its order, how it is drawn, one choice or several.
        $descriptivePlacements = $this->descriptivePlacements();

        $shape = fn ($grouped) => collect($grouped)->map(function ($options, $groupName) use ($profiles, $descriptiveIds, $businessId, $descriptivePlacements) {
            $groupId = (int) $options->first()->group_id;
            $groupNameEn = $options->first()->group_name_en;

            return [
                'group_id' => $groupId,
                'group_name' => app()->getLocale() === 'en' && $groupNameEn
                    ? (string) $groupNameEn
                    : (string) $groupName,
                // "ماركات الموبيلات", "ماركات السيارات", "ماركات الأجهزة
                // الكهربائية"... — every brand vocabulary on the platform is
                // named this way, so the item form can single one out as ITS
                // OWN dropdown instead of just another modifier chip: a
                // merchant chooses the brand, not just qualifies with it.
                'is_brand' => str_contains((string) $groupName, 'ماركات') || str_contains((string) $groupName, 'العلامة التجارية'),
                // «حالة المنتج» (جديد/مستعمل) — singled out the same way
                // `is_brand` is, so «التسعير والتفاصيل» can single-select it
                // as its own condition toggle instead of a generic modifier
                // chip. See [[tech-spec-menu-implementation]].
                'is_condition' => str_contains((string) $groupName, 'حالة المنتج'),
                // «منيو أساسي» or «منيو تفصيلي» — set per group in «أشكال
                // المنيو» (MenuDetailProfile). true opens «التسعير والتفاصيل»
                // (catalog-linked) for this group's branches instead of the
                // plain quantity/price dialog; `detail_profile` names which
                // details, so that screen shows a car's fields for a car and a
                // phone's for a phone. See [[tech-spec-menu-implementation]].
                'detailed' => isset($profiles[$groupId]),
                // true = «مكونات الخدمة» made this group DESCRIBE what the item
                // is for this trade («مودرن»، «زان» on a bedroom) — a client that
                // sees any opens the full item form (description, choices,
                // photos) instead of the quick name-and-price one.
                'descriptive' => in_array($groupId, $descriptiveIds, true),
                // How this trade's descriptive field is drawn (auto | chips |
                // dropdown), whether it takes one choice or several, and where it
                // sits among the others — set in «مكونات الخدمة», never per kind.
                'display' => $descriptivePlacements[$groupId]->display ?? 'auto',
                'multiple' => (bool) ($descriptivePlacements[$groupId]->multiple ?? true),
                'descriptive_sort' => (int) ($descriptivePlacements[$groupId]->sort_order ?? 0),
                'detail_profile' => $profiles[$groupId] ?? null,
                // true = «مكونات الخدمة» → «فروع المجموعة أقسام» is ticked for this
                // group: «غرفة نوم»، «سفرة»، «أنتريه» are the merchant's SECTIONS —
                // each its own heading with its cards and «إضافة منتج» under them.
                // The one switch that decides it; never guessed from the kind.
                'branches_as_sections' => $this->sectionFromGroup->isSplit($businessId, $groupId),
                // null = every SaleUnits::options() code is fair game; see
                // MenuMarketCatalogService's identical check for why produce
                // groups narrow down (SaleUnits::producePackagingGroupNames()).
                'sale_unit_codes' => in_array($groupName, SaleUnits::producePackagingGroupNames(), true)
                    ? SaleUnits::herbsCodes() : null,
                'options' => collect($options)->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'name_ar' => $o->name_ar,
                    'name_en' => $o->name_en,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'lines' => $shape($vocabulary['lines']),
                'modifiers' => $shape($vocabulary['modifiers']),
                'price_axes' => $this->priceAxes($businessId),
            ],
        ]);
    }

    /**
     * «اختيار الوصف ونوع الخشب من مجموعات الخيارات» — المالك، 2026-10-02. A
     * furniture factory sells «غرفة نوم»; «مودرن» and «زان» DESCRIBE it. The
     * vocabulary offers every ticked option in `lines` too (the role is an
     * ordering, not a permission), so a group «مكونات الخدمة» made
     * `descriptive` for this child — «أنواع الأخشاب»، «طراز الأثاث» — sat in
     * the «what is it» list beside the bedroom, and the app (which keeps
     * `lines` groups out of the qualifier chips) could never offer it as a
     * description: the merchant had to choose «غرفة نوم» OR «زان». They stay
     * in `modifiers`; only `lines` drops them.
     *
     * Never empties `lines`: a child whose every group is descriptive keeps
     * them all, as before.
     *
     * @param  \Illuminate\Support\Collection<string,\Illuminate\Support\Collection>  $lines
     * @param  list<int>  $descriptive
     * @return \Illuminate\Support\Collection<string,\Illuminate\Support\Collection>
     */
    private function withoutDescriptiveGroups($lines, array $descriptive)
    {
        $kept = $lines->reject(fn ($options) => in_array((int) $options->first()->group_id, $descriptive, true));

        return $kept->isNotEmpty() ? $kept : $lines;
    }

    /** @return list<int> the option groups «مكونات الخدمة» made descriptive for this child under the menu service */
    /** option_group_id => the trade's DESCRIPTIVE placement of it (menu service), in its order */
    private function descriptivePlacements(): array
    {
        return $this->placementsFor(\App\Models\ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE);
    }

    /**
     * «طريقة السداد كاش وتقسيط وهم سعرين مختلفين» — المالك، 2026-10-03: the groups
     * «مكونات الخدمة» made a PRICE AXIS for this trade (usage price_variant), in
     * its order, each with the options this merchant offers (what he ticked, else
     * all of them). The item form asks for one price per option; they are kept as
     * the item's variants and the customer picks among them on the product page.
     * Not part of `modifiers`: «كاش» is a business-level word, not a product's.
     *
     * @return list<array{group_id:int,group_name:string,options:list<array{id:int,name_ar:string,name_en:?string}>}>
     */
    private function priceAxes(int $businessId): array
    {
        $axes = [];
        $ticked = DB::table('option_user')->where('user_id', $businessId)->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        foreach ($this->placementsFor(\App\Models\ServiceOptionGroupPlacement::USAGE_PRICE_VARIANT) as $groupId => $placement) {
            $group = OptionGroup::query()->find($groupId, ['id', 'name_ar', 'name_en']);
            if (! $group) {
                continue;
            }

            $options = DB::table('options')->where('group_id', $groupId)->orderBy('id')->get(['id', 'name_ar', 'name_en']);
            $mine = $options->filter(fn ($o) => in_array((int) $o->id, $ticked, true));
            $offered = $mine->isNotEmpty() ? $mine : $options;

            $axes[] = [
                'group_id' => (int) $groupId,
                'group_name' => (string) $group->displayName(),
                'options' => $offered->map(fn ($o) => ['id' => (int) $o->id, 'name_ar' => (string) $o->name_ar, 'name_en' => $o->name_en])->values()->all(),
            ];
        }

        return $axes;
    }

    /** option_group_id => this trade's placement of it for [$usage] under the menu service, in order */
    private function placementsFor(string $usage): array
    {
        $childId = $this->childId();
        $menuServiceId = (int) \App\Models\PlatformService::query()->where('key', \App\Models\PlatformService::KEY_MENU)->value('id');

        if ($childId <= 0 || $menuServiceId <= 0) {
            return [];
        }

        return app(\App\Services\Catalog\ServiceOptionPlacements::class)
            ->for($menuServiceId, $childId, $usage)
            ->keyBy('option_group_id')->all();
    }

    private function descriptiveGroupIds(): array
    {
        $childId = $this->childId();
        $menuServiceId = (int) \App\Models\PlatformService::query()->where('key', \App\Models\PlatformService::KEY_MENU)->value('id');

        if ($childId <= 0 || $menuServiceId <= 0) {
            return [];
        }

        return app(\App\Services\Catalog\ServiceOptionPlacements::class)
            ->for($menuServiceId, $childId, \App\Models\ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE)
            ->pluck('option_group_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * option_group_id => its «منيو تفصيلي» profile and fields, for the groups
     * given that have one. A group missing from the result is «منيو أساسي».
     *
     * @param  list<int>  $groupIds
     * @return array<int, array<string,mixed>>
     */
    private function detailProfilesFor(array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }

        $english = app()->getLocale() === 'en';

        $profiles = \App\Models\MenuDetailProfile::query()
            ->join('option_groups as g', 'g.menu_detail_profile_id', '=', 'menu_detail_profiles.id')
            ->whereIn('g.id', $groupIds)
            ->where('menu_detail_profiles.is_active', true)
            ->get(['menu_detail_profiles.*', 'g.id as group_id']);

        $fields = \App\Models\MenuDetailProfile::fieldsFor($profiles->pluck('id')->unique()->values()->all(), $english);

        return $profiles->mapWithKeys(fn ($p) => [(int) $p->group_id => [
            'id' => (int) $p->id,
            'code' => (string) $p->code,
            'name' => $p->label($english),
            'icon' => $p->icon,
            // false = no catalog behind this kind (a bedroom has no «model» to
            // pick): the merchant names the item and states EVERY field himself.
            'uses_catalog' => (bool) $p->uses_catalog,
            'fields' => collect($fields[(int) $p->id] ?? [])
                ->map(fn ($f) => $p->uses_catalog ? $f : ['per_item' => true] + $f)->all(),
        ]])->all();
    }

    /**
     * GET /api/v2/business/menu/available-types — every `line` option this
     * business's (root, child) is allowed to carry AT ALL, grouped by
     * section, each flagged `selected` by the business's own `option_user`
     * ticks. Unlike `vocabulary()` (narrowed to what is already ticked, or
     * the child's whole list when nothing is), this is the FULL universe —
     * it powers the checklist a merchant uses to grow that narrowed set,
     * e.g. a greengrocer who only ticked 29 of 122 vegetable/fruit kinds
     * when the account was created and has no other way to add the rest.
     */
    public function availableTypes(Request $request)
    {
        $businessId = $this->businessId($request);
        $allowed = $this->childScope->idsFor($this->childId(), $this->rootId());

        if ($allowed->isEmpty()) {
            return response()->json(['success' => true, 'data' => ['groups' => []]]);
        }

        $ticked = DB::table('option_user')->where('user_id', $businessId)
            ->pluck('option_id')->map(fn ($id) => (int) $id);

        $rows = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('o.id', $allowed)
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->where('g.is_active', 1)
            ->orderByRaw('COALESCE(g.reorder, 999999) ASC')
            ->orderBy('o.id')
            ->get(['o.id', 'o.name_ar', 'o.name_en', 'g.id as group_id', 'g.name_ar as group_name', 'g.name_en as group_name_en']);

        $groups = $rows->groupBy('group_id')->map(fn ($options) => [
            'group_id' => (int) $options->first()->group_id,
            'group_name' => app()->getLocale() === 'en' && $options->first()->group_name_en
                ? (string) $options->first()->group_name_en
                : (string) $options->first()->group_name,
            'options' => $options->map(fn ($o) => [
                'id' => (int) $o->id,
                'name_ar' => $o->name_ar,
                'name_en' => $o->name_en,
                'selected' => $ticked->contains((int) $o->id),
            ])->values(),
        ])->values();

        return response()->json(['success' => true, 'data' => ['groups' => $groups]]);
    }

    /**
     * PUT /api/v2/business/menu/available-types — set exactly which options
     * WITHIN ONE section this business carries. Scoped to that section's own
     * option ids only: every other section's ticks, and every non-`line`
     * tick `ProfileController::updateOptions()` owns, are left untouched.
     */
    public function updateAvailableTypes(Request $request)
    {
        $businessId = $this->businessId($request);

        $data = $request->validate([
            'group_id' => ['required', 'integer'],
            'option_ids' => ['present', 'array'],
            'option_ids.*' => ['integer', 'min:1'],
        ]);

        $allowed = $this->childScope->idsFor($this->childId(), $this->rootId());

        $groupOptionIds = DB::table('options')
            ->where('group_id', (int) $data['group_id'])
            ->whereIn('id', $allowed)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $wanted = collect($data['option_ids'])
            ->map(fn ($id) => (int) $id)
            ->intersect($groupOptionIds)
            ->values();

        DB::transaction(function () use ($businessId, $groupOptionIds, $wanted) {
            DB::table('option_user')->where('user_id', $businessId)->whereIn('option_id', $groupOptionIds)->delete();

            $rows = $wanted->map(fn ($id) => ['user_id' => $businessId, 'option_id' => $id])->values()->all();
            foreach (array_chunk($rows, 200) as $chunk) {
                if ($chunk) {
                    DB::table('option_user')->insertOrIgnore($chunk);
                }
            }
        });

        return response()->json([
            'success' => true,
            'data' => ['group_id' => (int) $data['group_id'], 'selected_ids' => $wanted->values()],
        ]);
    }

    /**
     * GET /api/v2/business/menu/display-mode — how this business's menu
     * renders for a customer: a plain row per item ('list', the default) or
     * a 2-column card grid ('grid'). One merchant-wide switch — see
     * BusinessMenuSetting::DISPLAY_MODES.
     */
    public function displayMode(Request $request)
    {
        $mode = BusinessMenuSetting::query()
            ->where('business_id', $this->businessId($request))
            ->value('display_mode');

        return response()->json([
            'success' => true,
            'data' => ['display_mode' => $mode ?: BusinessMenuSetting::DISPLAY_LIST],
        ]);
    }

    /** PUT /api/v2/business/menu/display-mode */
    public function updateDisplayMode(Request $request)
    {
        $data = $request->validate([
            'display_mode' => ['required', Rule::in(BusinessMenuSetting::DISPLAY_MODES)],
        ]);

        BusinessMenuSetting::updateOrCreate(
            ['business_id' => $this->businessId($request)],
            ['display_mode' => $data['display_mode']]
        );

        return response()->json(['success' => true, 'data' => ['display_mode' => $data['display_mode']]]);
    }

    /**
     * GET /api/v2/business/menu/sale-units — the vocabulary a base_price can
     * be priced "per" (كجم، لتر، قطعة...), for the item form's own dropdown.
     * Null/omitted means the item is priced by the piece, as most are.
     */
    public function saleUnits(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'units' => collect(SaleUnits::options())
                    ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                    ->values(),
            ],
        ]);
    }

    /** GET /api/v2/business/menu/items */
    public function index(Request $request)
    {
        $businessId = $this->businessId($request);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'menu_section_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $items = MenuItem::query()
            ->where('business_id', $businessId)
            ->when(isset($data['is_active']), fn ($q) => $q->where('is_active', (bool) $data['is_active']))
            ->when($data['menu_section_id'] ?? null, fn ($q, $s) => $q->where('menu_section_id', $s))
            ->when($data['q'] ?? null, function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->where(fn ($sub) => $sub->whereRaw('LOWER(name_ar) LIKE ?', [$like])->orWhereRaw('LOWER(name_en) LIKE ?', [$like]));
            })
            ->with('images')
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return MenuItemResource::collection($items)->additional(['success' => true]);
    }

    /** GET /api/v2/business/menu/items/{item} */
    public function show(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $model->load([
            'variants' => fn ($q) => $q->orderBy('id'),
            'extraGroups',
            'extras' => fn ($q) => $q->orderBy('id'),
            'images',
        ]);

        return (new MenuItemResource($model))->additional(['success' => true]);
    }

    /** POST /api/v2/business/menu/items */
    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $this->validatedItem($request, $businessId);

        // One transaction: a wrong «سنة الصنع» is a 422 and the item is not
        // left half-saved without it.
        $item = DB::transaction(function () use ($request, $data, $businessId) {
            $item = MenuItem::create($data + ['business_id' => $businessId]);

            if (! $item->medicine_id) {
                $this->applyVocabulary($request, $item, explicitSection: $data['menu_section_id'] !== null);
                $this->syncDetailAttributes($request, $item);
            }

            return $item;
        });

        return (new MenuItemResource($item->fresh()))->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item} */
    public function update(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $data = $this->validatedItem($request, $this->businessId($request));

        DB::transaction(function () use ($request, $model, $data) {
            $model->update($data);

            if (! $model->medicine_id) {
                $this->applyVocabulary($request, $model, explicitSection: $data['menu_section_id'] !== null);
                $this->syncDetailAttributes($request, $model);
            }
        });

        return (new MenuItemResource($model->fresh()))->additional(['success' => true]);
    }

    /**
     * The values this UNIT carries — a car's year, mileage, gearbox, colour —
     * sent as `attributes: {attribute_id: value}`. Only the fields its detail
     * kind flags `per_item` are accepted ({@see MenuItemAttributes}); an item
     * under a basic menu has none, so the key is ignored there. Absent key =
     * untouched, so an edit that only changes the price never wipes them.
     */
    private function syncDetailAttributes(Request $request, MenuItem $item): void
    {
        if (! $request->has('attributes') || ! is_array($request->input('attributes'))) {
            return;
        }

        $groupId = (int) ($item->lineOption()?->group_id ?? 0);
        $fields = $this->detailProfilesFor($groupId > 0 ? [$groupId] : [])[$groupId]['fields'] ?? [];

        app(MenuItemAttributes::class)->sync($item, $request->input('attributes'), $fields);
    }

    /**
     * Store what the item IS (a `line` option, e.g. "ثلاجات") and what
     * qualifies it (`modifier` options — brand, condition...), refusing
     * anything outside this merchant's own vocabulary. Mirrors the web
     * panel's `MenuItemController::applyVocabulary()` — one behavior, two
     * doors.
     *
     * When the merchant did not name a section by hand, the line option's
     * own group becomes one — {@see MenuSectionFromOptionGroup} — so a goods
     * business never types "أنواع الأجهزة الكهربائية" itself. A merchant
     * typing a brand-new item name (no line option of its own) may instead
     * send `option_group_id` directly — «اضافة صنف» flow — resolved the
     * same way, gated to this merchant's own vocabulary groups so an
     * arbitrary id can't grow a section from unrelated data.
     */
    private function applyVocabulary(Request $request, MenuItem $item, bool $explicitSection): void
    {
        $businessId = $this->businessId($request);
        $picks = $this->vocabulary->pickableIds($businessId, $this->childId(), $this->rootId());

        $line = (int) $request->input('line_option_id', 0);
        $line = $picks['lines']->contains($line) ? $line : null;

        $modifiers = collect($request->input('modifier_option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $picks['modifiers']->contains($id))
            ->values()
            ->all();

        $item->syncOfferingOptions($line, $modifiers, $item->currentOfferingAdjustments());

        if ($explicitSection) {
            return;
        }

        if ($line) {
            $lineOption = $item->lineOption();
            $section = $lineOption ? $this->sectionFromGroup->resolve($businessId, $lineOption) : null;
        } else {
            $groupId = (int) $request->input('option_group_id', 0);
            $allowedGroupIds = $this->vocabulary->for($businessId, $this->childId(), $this->rootId())['lines']
                ->flatten(1)->pluck('group_id')->unique();
            $section = $groupId && $allowedGroupIds->contains($groupId)
                ? $this->sectionFromGroup->resolveGroupId($businessId, $groupId)
                : null;
        }

        if ($section && (int) $item->menu_section_id !== (int) $section->id) {
            $item->forceFill(['menu_section_id' => $section->id])->saveQuietly();
        }
    }

    /**
     * GET /api/v2/business/menu/catalog-lookup — real catalog masters a
     * merchant may LINK a menu item to (never writes anything), so a mobile
     * or laptop shop picks a real model instead of retyping its specs. Read
     * access only.
     *
     * Scoped to the owner's own retail catalog (same `retailScope()` idea
     * BusinessRetailListingController already uses) — «حالة اختبار على
     * المحاكي» surfaced a real bug: an unscoped lookup let a mobiles shop's
     * «التسعير والتفاصيل» search turn up refrigerators and spice jars right
     * alongside phones, since catalog_products spans the WHOLE platform
     * catalog. See [[tech-spec-menu-implementation]].
     */
    public function catalogLookup(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            // The branch the picker was opened from («موبايل», «تابلت»…).
            'line_option_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'series' => ['nullable', 'string', 'max:80'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        $brandId = (int) ($data['brand_id'] ?? 0);
        $series = trim((string) ($data['series'] ?? ''));
        $businessId = $this->businessId($request);

        // Empty = this business's own retail scope resolved to nothing worth
        // narrowing by (no retail service, or one configured with no allowed
        // types) — matching the platform-wide "narrowing to empty is worse
        // than none" convention, the lookup stays UNSCOPED rather than
        // returning zero results.
        $scope = $this->catalogScope();

        $base = fn () => \App\Models\CatalogProduct::query()
            ->active()
            ->whereNull('catalog_products.deleted_at')
            ->when(! empty($scope), fn ($q) => $q->whereIn('catalog_products.product_category_child_id', $scope))
            // Approved masters, plus the models THIS business proposed and an
            // admin has not reviewed yet — see proposeProduct().
            ->where(fn ($q) => $q->where('catalog_products.approval_status', 'approved')
                ->orWhere(fn ($own) => $own->where('catalog_products.approval_status', 'pending')
                    ->where('catalog_products.created_by', $businessId)));

        /*
         * «تابلت» shows tablets, not the whole «موبايلات وإكسسوارات» shelf.
         * Only once the branch has been filed at all: a branch no product
         * points at yet (every non-device vertical today) keeps the whole
         * scope instead of opening onto an empty picker.
         */
        $branch = (int) ($data['line_option_id'] ?? 0);
        if ($branch > 0 && ! $base()->where('catalog_products.line_option_id', $branch)->exists()) {
            $branch = 0;
        }

        $inBranch = fn () => $base()->when($branch > 0, fn ($q) => $q->where('catalog_products.line_option_id', $branch));

        $items = $inBranch()
            ->when($brandId > 0, fn ($q) => $q->where('catalog_products.brand_id', $brandId))
            ->when($series !== '', fn ($q) => $q->where('catalog_products.series', $series))
            ->when($term !== '', fn ($q) => $q->search($term))
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'catalog_products.brand_id')
            ->orderBy('b.name_en')
            ->orderBy('catalog_products.series')
            ->orderBy('catalog_products.name_en')
            ->limit(60)
            ->get(self::LOOKUP_COLUMNS);

        // Batched, not per-row — the same reasoning ProductSpecs's own
        // docblock gives: one query pair for the whole page of results.
        $specs = app(\App\Services\Catalog\ProductSpecs::class)->forProducts($items->pluck('id')->all());

        $items = $items->map(fn ($p) => $this->catalogProductPayload($p, $specs[(int) $p->id] ?? []));

        return response()->json(['success' => true, 'data' => [
            'items' => $items,
            // The chip rows above the list: brand first, then that brand's
            // series («أوبو» → A · F · Reno · Find). Counted inside the branch
            // so a tablet picker never offers a brand that makes no tablets.
            'facets' => [
                'brands' => $this->brandFacets($inBranch()),
                'series' => $brandId > 0 ? $this->seriesFacets($inBranch()->where('catalog_products.brand_id', $brandId)) : [],
            ],
        ]]);
    }

    private const LOOKUP_COLUMNS = [
        'catalog_products.id', 'catalog_products.name_ar', 'catalog_products.name_en', 'catalog_products.main_image',
        'catalog_products.brand_id', 'catalog_products.series', 'catalog_products.approval_status', 'b.name_ar as brand_name',
    ];

    /**
     * POST /api/v2/business/menu/catalog-products — «الموديل مش موجود».
     *
     * The merchant names the brand, the series and the model and types the
     * specs himself. The row is a real catalog master from the first second
     * (his menu item links to it like any other), but PENDING: only he sees
     * it in the picker until an admin approves it in «كتالوج المنتجات», after
     * which every shop in the branch can pick it. That is how the catalog
     * grows past the popular models seeded on day one without filling up
     * with duplicates and typos.
     */
    public function proposeProduct(Request $request)
    {
        $data = $request->validate([
            'line_option_id' => ['required', 'integer', Rule::exists('options', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('catalog_brands', 'id')->whereNull('deleted_at')],
            'brand_name' => ['nullable', 'required_without:brand_id', 'string', 'max:120'],
            'series' => ['nullable', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:160'],
            'processor' => ['nullable', 'string', 'max:120'],
            'ram_gb' => ['nullable', 'numeric', 'min:0', 'max:1024'],
            'storage' => ['nullable', 'string', 'max:40'],
            'screen_inches' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'os' => ['nullable', 'string', 'max:60'],
            'rear_camera_mp' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'front_camera_mp' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'battery_mah' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $businessId = $this->businessId($request);
        $branch = (int) $data['line_option_id'];

        // The shelf the new model lives on: where this branch's products
        // already are, else the first one this business's catalog allows.
        $childId = (int) DB::table('catalog_products')->where('line_option_id', $branch)->whereNull('deleted_at')->value('product_category_child_id')
            ?: (int) (($this->catalogScope() ?? [])[0] ?? 0);

        abort_if($childId <= 0, 422, 'لا يوجد كتالوج مرتبط بهذا الفرع.');

        $child = DB::table('product_category_children')->where('id', $childId)->first(['id', 'product_category_id']);

        $brand = isset($data['brand_id'])
            ? DB::table('catalog_brands')->where('id', $data['brand_id'])->first(['id', 'name_ar', 'name_en'])
            : $this->brandByName(trim((string) $data['brand_name']));

        $model = trim((string) $data['model']);
        $series = trim((string) ($data['series'] ?? '')) ?: null;
        $nameEn = trim($brand->name_en . ' ' . $model);
        $nameAr = trim(($brand->name_ar ?: $brand->name_en) . ' ' . $model);

        // The same model twice is one product — his own earlier proposal, or
        // one the catalog already has.
        $existing = (int) DB::table('catalog_products')
            ->where('product_category_child_id', $childId)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereRaw('LOWER(name_en) = ?', [mb_strtolower($nameEn)])->orWhere('name_ar', $nameAr))
            ->where(fn ($q) => $q->where('approval_status', 'approved')->orWhere('created_by', $businessId))
            ->value('id');

        $productId = $existing ?: (int) DB::table('catalog_products')->insertGetId([
            'bim_code' => 'BIM-MR-' . strtoupper(\Illuminate\Support\Str::random(10)),
            'product_category_id' => $child->product_category_id,
            'product_category_child_id' => $childId,
            'brand_id' => $brand->id,
            'series' => $series,
            'line_option_id' => $branch,
            'product_type' => 'simple',
            'name_ar' => $nameAr,
            'normalized_name_ar' => mb_strtolower($nameAr),
            'name_en' => $nameEn,
            'normalized_name_en' => mb_strtolower($nameEn),
            'model' => $model,
            'image_alt_ar' => $nameAr,
            'image_alt_en' => $nameEn,
            'market_scope' => 'egypt',
            'country_code' => 'EG',
            'is_verified_egypt' => 0,
            'verification_source' => 'merchant',
            'duplicate_status' => 'review',
            'is_active' => 1,
            'approval_status' => 'pending',
            'created_by' => $businessId,
            'updated_by' => $businessId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $existing) {
            $this->writeProposedSpecs($productId, $data);
        }

        $row = \App\Models\CatalogProduct::query()
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'catalog_products.brand_id')
            ->where('catalog_products.id', $productId)
            ->first(self::LOOKUP_COLUMNS);

        $specs = app(\App\Services\Catalog\ProductSpecs::class)->forProducts([$productId]);

        return response()->json([
            'success' => true,
            'data' => ['product' => $this->catalogProductPayload($row, $specs[$productId] ?? [])],
        ], $existing ? 200 : 201);
    }

    /** @return array<string,mixed> */
    private function catalogProductPayload(object $p, array $specs): array
    {
        return [
            'id' => (int) $p->id,
            'name' => app()->getLocale() === 'en' ? ($p->name_en ?: $p->name_ar) : ($p->name_ar ?: $p->name_en),
            'brand' => $p->brand_name,
            'brand_id' => $p->brand_id ? (int) $p->brand_id : null,
            'series' => $p->series,
            'image' => $p->main_image,
            // true = proposed by this business and not reviewed yet.
            'pending' => $p->approval_status !== 'approved',
            // So the item form can show a spec preview the instant a merchant
            // picks a result, before saving anything — see
            // [[tech-spec-menu-implementation]]'s «التسعير والتفاصيل».
            'specs' => $specs,
        ];
    }

    /** @return list<array{id:int,name:string,count:int}> */
    private function brandFacets($query): array
    {
        $english = app()->getLocale() === 'en';

        return $query
            ->join('catalog_brands as fb', 'fb.id', '=', 'catalog_products.brand_id')
            ->groupBy('fb.id', 'fb.name_ar', 'fb.name_en')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('fb.name_en')
            ->get(['fb.id', 'fb.name_ar', 'fb.name_en', DB::raw('COUNT(*) as n')])
            ->map(fn ($b) => [
                'id' => (int) $b->id,
                'name' => (string) ($english ? ($b->name_en ?: $b->name_ar) : ($b->name_ar ?: $b->name_en)),
                'count' => (int) $b->n,
            ])->values()->all();
    }

    /** @return list<array{name:string,count:int}> */
    private function seriesFacets($query): array
    {
        return $query
            ->whereNotNull('catalog_products.series')
            ->where('catalog_products.series', '!=', '')
            ->groupBy('catalog_products.series')
            ->orderBy('catalog_products.series')
            ->get(['catalog_products.series', DB::raw('COUNT(*) as n')])
            ->map(fn ($s) => ['name' => (string) $s->series, 'count' => (int) $s->n])
            ->values()->all();
    }

    /** A brand the merchant typed: matched by either name, else created unverified. */
    private function brandByName(string $name): object
    {
        $found = DB::table('catalog_brands')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereRaw('LOWER(name_en) = ?', [mb_strtolower($name)])->orWhere('name_ar', $name))
            ->first(['id', 'name_ar', 'name_en']);

        if ($found) {
            return $found;
        }

        $slug = \Illuminate\Support\Str::slug($name) ?: 'brand';
        for ($base = $slug, $n = 2; DB::table('catalog_brands')->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        $latin = preg_match('/^[\x20-\x7E]+$/', $name) === 1;
        $id = DB::table('catalog_brands')->insertGetId([
            'name_ar' => $latin ? null : $name,
            'name_en' => $name,
            'slug' => $slug,
            'is_active' => 1,
            'is_verified' => 0,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id, 'name_ar' => $latin ? null : $name, 'name_en' => $name];
    }

    /** The spec rows a merchant typed — TechDeviceSpecsSeeder's attributes plus MobileDeviceCatalogSeeder's camera/battery. */
    private function writeProposedSpecs(int $productId, array $data): void
    {
        $attrs = DB::table('catalog_attributes')
            ->whereIn('code', ['processor', 'ram_gb', 'storage', 'screen_inches', 'os', 'rear_camera_mp', 'front_camera_mp', 'battery_mah'])
            ->pluck('id', 'code');
        $units = DB::table('catalog_units')->whereIn('code', ['gb', 'inch', 'mp', 'mah'])->pluck('id', 'code');

        $values = [
            'processor' => [null, $data['processor'] ?? null, null],
            'ram_gb' => [$data['ram_gb'] ?? null, null, $units['gb'] ?? null],
            'storage' => [null, $data['storage'] ?? null, null],
            'screen_inches' => [$data['screen_inches'] ?? null, null, $units['inch'] ?? null],
            'os' => [null, $data['os'] ?? null, null],
            'rear_camera_mp' => [$data['rear_camera_mp'] ?? null, null, $units['mp'] ?? null],
            'front_camera_mp' => [$data['front_camera_mp'] ?? null, null, $units['mp'] ?? null],
            'battery_mah' => [$data['battery_mah'] ?? null, null, $units['mah'] ?? null],
        ];

        foreach ($values as $code => [$number, $text, $unit]) {
            $text = $text === null ? null : (trim((string) $text) ?: null);

            if ((($number ?? '') === '' && $text === null) || ! isset($attrs[$code])) {
                continue;
            }

            DB::table('catalog_product_attribute_values')->insert([
                'product_id' => $productId,
                'attribute_id' => (int) $attrs[$code],
                'value_number' => ($number ?? '') === '' ? null : (float) $number,
                'value_text_en' => $text,
                'unit_id' => $unit,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * The product_category_children ids this owner's RETAIL service allows —
     * the catalog scope a menu item's product picker narrows to, regardless
     * of whether the sale itself goes through menu or retail.
     *
     * Deliberately NOT read through `servicesForChild()`/`retailScope()`
     * (which both require the retail LINK to be active) — `allowed_item_types`
     * on retail's own `CategoryServiceConfig` row just records which catalog
     * category this business's child deals in (the taxonomy fact
     * [[retail-service-build]]'s branch↔slug mirror captures), independent of
     * whether the owner currently SELLS through retail itself. A menu-only
     * detailed business — retail off, menu on, exactly «منيو مواصفات» 1/2's
     * own real shape — must still scope its product picker correctly; a real
     * mobiles shop (child 186) surfaced this live: retail sat inactive for
     * it (an unrelated config edit), so the old retail-gated lookup silently
     * fell back to unscoped and a phone search returned spice jars.
     *
     * @return array<int>|null null = no allowed types configured at all —
     *         nothing to narrow by, so the caller should leave the lookup
     *         unscoped rather than returning zero results.
     */
    private function catalogScope(): ?array
    {
        $childId = $this->childId();

        if ($childId <= 0) {
            return null;
        }

        $retailId = \App\Models\PlatformService::query()
            ->where('key', \App\Models\PlatformService::KEY_RETAIL)
            ->value('id');

        if (! $retailId) {
            return null;
        }

        $config = \App\Models\CategoryServiceConfig::query()
            ->where('child_id', $childId)
            ->where('category_id', $this->rootId())
            ->where('platform_service_id', $retailId)
            ->value('config');

        $data = is_array($config) ? $config : (json_decode((string) $config, true) ?: []);
        $typeKeys = collect($data['allowed_item_types'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->all();

        if (empty($typeKeys)) {
            return null;
        }

        $ids = DB::table('product_category_children')
            ->whereIn('slug', $typeKeys)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids ?: null;
    }

    /** DELETE /api/v2/business/menu/items/{item} */
    public function destroy(Request $request, int $item)
    {
        $this->ownItem($request, $item)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Variants ───────────────────────────

    /** POST /api/v2/business/menu/items/{item}/variants */
    public function storeVariant(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $data = $this->validatedVariant($request);

        if ($data['is_default']) {
            $model->variants()->update(['is_default' => false]);
        }
        $variant = $model->variants()->create($data);

        return response()->json(['success' => true, 'data' => ['id' => (int) $variant->id]], 201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item}/variants/{variant} */
    public function updateVariant(Request $request, int $item, int $variant)
    {
        $model = $this->ownItem($request, $item);
        $row = MenuItemVariant::query()->where('menu_item_id', $model->id)->findOrFail($variant);
        $data = $this->validatedVariant($request);

        if ($data['is_default']) {
            $model->variants()->where('id', '!=', $row->id)->update(['is_default' => false]);
        }
        $row->update($data);

        return response()->json(['success' => true]);
    }

    /** DELETE /api/v2/business/menu/items/{item}/variants/{variant} */
    public function destroyVariant(Request $request, int $item, int $variant)
    {
        $model = $this->ownItem($request, $item);
        MenuItemVariant::query()->where('menu_item_id', $model->id)->findOrFail($variant)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Images ───────────────────────────

    /**
     * POST /api/v2/business/menu/items/{item}/images
     *
     * A gallery, not a photo. One picture sells no apartment and no car, and
     * `menu_items.image` — the single column that was there — was never written
     * by any screen, so every listing on the platform is text.
     *
     * Appends. Replacing the gallery is deleting what you do not want, which is
     * one call away and cannot be done by accident.
     */
    public function storeImages(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . self::MAX_IMAGES],
            'images.*' => ImageUploadService::validationRules(),
            // camera = taken live in the app's camera, never picked from the
            // gallery — the same claim an album photo makes.
            'source' => ['sometimes', Rule::in([Image::SOURCE_CAMERA, Image::SOURCE_UPLOAD])],
        ]);

        $source = (string) $request->input('source', Image::SOURCE_UPLOAD);

        // A second-hand unit is sold as itself — its photos are live shots,
        // or the buyer is looking at a catalogue picture of a phone that is
        // not the one he will receive. See MenuItem::isSecondHand().
        if ($source !== Image::SOURCE_CAMERA && $model->isSecondHand()) {
            return response()->json([
                'success' => false,
                'message' => __('صور المنتج المستعمل تُلتقط بالكاميرا مباشرة.'),
            ], 422);
        }

        $already = $model->images()->count();
        $incoming = count($request->file('images', []));

        if ($already + $incoming > self::MAX_IMAGES) {
            return response()->json([
                'success' => false,
                'message' => __('الحد الأقصى :max صور للصنف الواحد.', ['max' => self::MAX_IMAGES]),
            ], 422);
        }

        $uploads = app(ImageUploadService::class);
        $saved = [];

        foreach ($request->file('images') as $file) {
            $saved[] = $model->images()->create([
                'image' => $uploads->store($file),
                // A new item's photo is a picture of the goods and may come
                // from anywhere; `camera` is the merchant's claim of a live
                // shot, required (above) only for a second-hand unit.
                'source' => $source,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => ['images' => array_map(
                fn (Image $image) => ['id' => (int) $image->id, 'image' => $image->image, 'source' => $image->source],
                $saved
            )],
        ], 201);
    }

    /**
     * DELETE /api/v2/business/menu/items/{item}/images/{image}
     *
     * The file goes with the row. A row deleted alone leaves a picture on disk
     * that nothing can ever find again to remove.
     */
    public function destroyImage(Request $request, int $item, int $image)
    {
        $model = $this->ownItem($request, $item);
        $row = $model->images()->findOrFail($image);

        app(ImageUploadService::class)->delete($row->image);
        $row->delete();

        return response()->json(['success' => true]);
    }

    // ────────────────────────── Extra groups ──────────────────────────

    /** POST /api/v2/business/menu/items/{item}/extra-groups */
    public function storeExtraGroup(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $group = $model->extraGroups()->create($this->validatedExtraGroup($request));

        return response()->json(['success' => true, 'data' => ['id' => (int) $group->id]], 201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item}/extra-groups/{group} */
    public function updateExtraGroup(Request $request, int $item, int $group)
    {
        $model = $this->ownItem($request, $item);
        MenuItemExtraGroup::query()->where('menu_item_id', $model->id)->findOrFail($group)
            ->update($this->validatedExtraGroup($request));

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/v2/business/menu/items/{item}/extra-groups/{group}
     * Its extras are not deleted — they fall back to standalone (nullOnDelete
     * on extra_group_id), same as an item losing its menu section.
     */
    public function destroyExtraGroup(Request $request, int $item, int $group)
    {
        $model = $this->ownItem($request, $item);
        MenuItemExtraGroup::query()->where('menu_item_id', $model->id)->findOrFail($group)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Extras ───────────────────────────

    /** POST /api/v2/business/menu/items/{item}/extras */
    public function storeExtra(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $extra = $model->extras()->create($this->validatedExtra($request, $model));

        return response()->json(['success' => true, 'data' => ['id' => (int) $extra->id]], 201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item}/extras/{extra} */
    public function updateExtra(Request $request, int $item, int $extra)
    {
        $model = $this->ownItem($request, $item);
        MenuItemExtra::query()->where('menu_item_id', $model->id)->findOrFail($extra)
            ->update($this->validatedExtra($request, $model));

        return response()->json(['success' => true]);
    }

    /** DELETE /api/v2/business/menu/items/{item}/extras/{extra} */
    public function destroyExtra(Request $request, int $item, int $extra)
    {
        $model = $this->ownItem($request, $item);
        MenuItemExtra::query()->where('menu_item_id', $model->id)->findOrFail($extra)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    /** The acting business's id (owner, or a delegate's employer via business.member). */
    private function businessId(Request $request): int
    {
        return \App\Support\BusinessContext::id($request);
    }

    private function ownItem(Request $request, int $itemId): MenuItem
    {
        return MenuItem::query()
            ->where('business_id', $this->businessId($request))
            ->findOrFail($itemId);
    }

    /** @return array<string,mixed> */
    private function validatedItem(Request $request, int $businessId): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'menu_section_id' => ['nullable', 'integer', Rule::exists('menu_sections', 'id')->where('business_id', $businessId)],
            // «ربط نظام المواصفات مع المنيو» — an optional link to a real
            // catalog master (the same one retail's «كتالوج تفصيلي» prices),
            // so this item's spec table (processor/RAM/model…) comes from
            // catalog_product_attribute_values instead of being retyped.
            // An unreviewed proposal is linkable only by the business that made it.
            'catalog_product_id' => ['nullable', 'integer', Rule::exists('catalog_products', 'id')->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('approval_status', 'approved')->orWhere('created_by', $businessId))],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'supply_price' => ['nullable', 'numeric', 'min:0'],
            'sale_unit' => ['nullable', Rule::in(SaleUnits::codes())],
            'brand_name' => ['nullable', 'string', 'max:191'],
            // NULL means «لا أتابع الكمية» — a kitchen does not count
            // sandwiches. Zero is the other claim: «معروض، ونفد». Matches the
            // web panel's own ceiling — see Business\MenuItemController.
            'available_quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'menu_section_id' => ($data['menu_section_id'] ?? null) ?: null,
            'catalog_product_id' => ($data['catalog_product_id'] ?? null) ?: null,
            'description_ar' => trim((string) ($data['description_ar'] ?? '')) ?: null,
            'description_en' => trim((string) ($data['description_en'] ?? '')) ?: null,
            'base_price' => round((float) $data['base_price'], 2),
            'supply_price' => isset($data['supply_price']) && $data['supply_price'] !== ''
                ? round((float) $data['supply_price'], 2)
                : null,
            'available_quantity' => ($data['available_quantity'] ?? null) === null || $data['available_quantity'] === ''
                ? null
                : max(0, (int) $data['available_quantity']),
            // Empty string and «by the item» are the same answer; both store null.
            'sale_unit' => trim((string) ($data['sale_unit'] ?? '')) ?: null,
            'brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: $this->catalogBrandName($data['catalog_product_id'] ?? null),
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * «ماركات الموبايلات هل نقوم بنقل المجموعة الى الكتالوج ايضا كما قمنا
     * بنقل ماركات السيارات» — المالك، 2026-10-01. A catalog-linked item's
     * brand is already known — `catalog_products.brand_id` — the moment the
     * merchant picks a real product in «التسعير والتفاصيل», so it is never
     * asked for again through a separate «ماركات الموبيلات»/«ماركات
     * السيارات» modifier pick the way a hand-typed item still is. Only
     * fills what the merchant left blank — an explicit `brand_name` always
     * wins, matching the field's own long-standing meaning for every other
     * (non catalog-linked) item.
     */
    private function catalogBrandName(?int $catalogProductId): ?string
    {
        if (! $catalogProductId) {
            return null;
        }

        return DB::table('catalog_products')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'catalog_products.brand_id')
            ->where('catalog_products.id', $catalogProductId)
            ->value('b.name_ar');
    }

    /** @return array<string,mixed> */
    private function validatedVariant(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_delta' => ['nullable', 'numeric'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'type' => trim((string) $data['type']),
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'price' => isset($data['price']) ? round((float) $data['price'], 2) : null,
            'price_delta' => isset($data['price_delta']) ? round((float) $data['price_delta'], 2) : null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /** @return array<string,mixed> */
    private function validatedExtra(Request $request, MenuItem $item): array
    {
        $data = $request->validate([
            // Must belong to the SAME item — an id from someone else's menu
            // (or a different item of the caller's own) must not slip in.
            'extra_group_id' => ['nullable', 'integer', Rule::exists('menu_item_extra_groups', 'id')->where('menu_item_id', $item->id)],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'extra_group_id' => ($data['extra_group_id'] ?? null) ?: null,
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'price' => round((float) $data['price'], 2),
            'max_qty' => (int) ($data['max_qty'] ?? 1),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /** @return array<string,mixed> */
    private function validatedExtraGroup(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'selection_type' => ['required', Rule::in(MenuItemExtraGroup::SELECTION_TYPES)],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'selection_type' => $data['selection_type'],
            'reorder' => max(0, (int) ($data['reorder'] ?? 0)),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
