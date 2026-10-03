<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement;
use App\Services\Catalog\ProductSpecs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * «أشكال المنيو» — المالك، 2026-10-02: «منيو أساسي» (produce, spices — name,
 * price, quantity) vs «منيو تفصيلي» (mobiles, computers, cars… — a real
 * catalog product with its fields). Priced option groups on one side, a
 * phone on the other with every detail kind above it: try a group on each
 * kind, see the app's own «إضافة صنف» page change, and keep the one that
 * fits. The phone draws the app's real light theme, never the canvas's dark
 * mock-up frame — [[feedback_unified-theme-vertical-details]].
 *
 * Each detail kind's FIELDS are edited here too: they are what the merchant
 * fills, what the customer's product page lists, and what search filters on.
 */
class MenuShapesController extends Controller
{
    public const BASIC = 'basic';

    public function index(Request $request)
    {
        $profiles = MenuDetailProfile::query()->orderBy('sort_order')->orderBy('id')->get();
        $fields = MenuDetailProfile::fieldsFor($profiles->pluck('id')->all());

        $search = trim((string) $request->get('q', ''));
        $shape = (string) $request->get('shape', '');

        $groups = OptionGroup::query()
            ->where('price_role', OptionGroup::ROLE_LINE)
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")))
            ->when($shape === self::BASIC, fn ($q) => $q->whereNull('menu_detail_profile_id'))
            ->when($shape !== '' && $shape !== self::BASIC, fn ($q) => $q->where('menu_detail_profile_id', (int) $shape))
            ->withCount('options')
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'menu_detail_profile_id']);

        $group = $groups->firstWhere('id', (int) $request->get('group_id', 0)) ?: $groups->first();

        // Which shape the phone shows: what the admin is trying right now,
        // else what the group already has.
        $preview = (string) $request->get('preview', '');
        if ($preview === '') {
            $preview = $group && $group->menu_detail_profile_id ? (string) $group->menu_detail_profile_id : self::BASIC;
        }
        $previewProfile = $preview === self::BASIC ? null : $profiles->firstWhere('id', (int) $preview);
        if ($preview !== self::BASIC && ! $previewProfile) {
            $preview = self::BASIC;
        }

        $branches = $group
            ? DB::table('options')->where('group_id', $group->id)->orderBy('id')->get(['id', 'name_ar', 'name_en'])
            : collect();

        // The DESCRIPTIVE fields — decided in «مكونات الخدمة», per trade, never here.
        // Shown for what the trades that carry this group answered, so the phone
        // draws what the merchant and the customer will actually see.
        $menuServiceId = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        $groupPlacements = $group
            ? ServiceOptionGroupPlacement::query()->where('platform_service_id', $menuServiceId)->where('is_active', true)
                ->where('option_group_id', $group->id)->where('usage', ServiceOptionGroupPlacement::USAGE_SECTION)->get(['child_id', 'branches_as_sections'])
            : collect();
        $tradeIds = $groupPlacements->pluck('child_id')->unique()->all();
        $splitTrades = $groupPlacements->where('branches_as_sections', true)->pluck('child_id')->unique()->count();

        $descriptive = $group
            ? ServiceOptionGroupPlacement::query()->where('platform_service_id', $menuServiceId)->where('is_active', true)
                ->where('usage', ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE)
                ->when(! in_array(ServiceOptionGroupPlacement::ALL_CHILDREN, $tradeIds, true), fn ($q) => $q->whereIn('child_id', array_merge($tradeIds, [ServiceOptionGroupPlacement::ALL_CHILDREN])))
                ->orderBy('sort_order')->orderBy('id')->get()
            : collect();
        $describingSettings = $descriptive->groupBy('option_group_id')->map(fn ($rows) => [
            'show_on_page' => (bool) $rows->first()->show_on_page,
            'display' => (string) $rows->first()->display,
            'multiple' => (bool) $rows->first()->multiple,
            'trades' => $rows->pluck('child_id')->unique()->count(),
        ]);
        $chosenIds = $describingSettings->keys()->map(fn ($id) => (int) $id)->all();
        $describingGroups = OptionGroup::query()->whereIn('id', $chosenIds)->withCount('options')->get(['id', 'name_ar', 'name_en'])
            ->sortBy(fn ($g) => array_search((int) $g->id, $chosenIds, true))->values();

        return view('admin-v2.menu-shapes.index', [
            'splitTrades' => $splitTrades,
            'profiles' => $profiles,
            'fields' => $fields,
            'groups' => $groups,
            'group' => $group,
            'branches' => $branches,
            'preview' => $preview,
            'previewProfile' => $previewProfile,
            'describingGroups' => $describingGroups,
            // A sample value per group for the customer preview («مودرن» under «طراز الأثاث»).
            'describingSamples' => DB::table('options')->whereIn('group_id', $describingGroups->pluck('id')->all())
                ->orderBy('id')->get(['group_id', 'name_ar'])->groupBy('group_id')->map(fn ($rows) => (string) $rows->first()->name_ar),
            'describingChosen' => $chosenIds,
            'commonDescribing' => $this->commonDescribing($menuServiceId),
            'describingSettings' => $describingSettings,
            'sample' => $previewProfile && $group ? $this->sampleProduct($branches->pluck('id')->all(), $previewProfile) : null,
            'attributes' => DB::table('catalog_attributes as a')
                ->leftJoin('catalog_units as u', 'u.id', '=', 'a.unit_id')
                ->orderBy('a.sort_order')->orderBy('a.id')
                ->get(['a.id', 'a.code', 'a.name_ar', 'a.data_type', 'u.name_ar as unit']),
            'search' => $search,
            'shape' => $shape,
        ]);
    }

    /**
     * The «ويدجت» of descriptive option groups every menu carries — chosen once,
     * by hand, instead of group by group per trade in «مكونات الخدمة».
     * Stored as the menu service's all-trades descriptive placements (child 0),
     * which every trade already inherits; a trade's own row still overrides.
     *
     * @return array{rows:\Illuminate\Support\Collection,candidates:\Illuminate\Support\Collection}
     */
    private function commonDescribing(int $menuServiceId): array
    {
        $current = ServiceOptionGroupPlacement::query()
            ->where('platform_service_id', $menuServiceId)->where('usage', ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE)
            ->where('child_id', ServiceOptionGroupPlacement::ALL_CHILDREN)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()->keyBy('option_group_id');

        $scattered = ServiceOptionGroupPlacement::query()
            ->where('platform_service_id', $menuServiceId)->where('usage', ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE)
            ->where('child_id', '>', 0)->where('is_active', true)
            ->selectRaw('option_group_id, count(distinct child_id) as trades')->groupBy('option_group_id')->pluck('trades', 'option_group_id');

        $candidates = OptionGroup::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('price_role', OptionGroup::ROLE_DESCRIPTIVE)->orWhereIn('id', $current->keys()->merge($scattered->keys())->all()))
            ->withCount('options')->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
            ->each(fn ($g) => $g->trades = (int) ($scattered[$g->id] ?? 0))
            // chosen ones first, in their saved order
            ->sortBy(fn ($g) => $current->has($g->id) ? [0, array_search($g->id, $current->keys()->all(), true)] : [1, $g->name_ar])->values();

        return ['rows' => $current, 'candidates' => $candidates];
    }

    /** POST — save the common descriptive groups; optionally fold the per-trade copies into it. */
    public function saveCommonDescribing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*.enabled' => ['nullable', 'boolean'],
            'groups.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'groups.*.show_on_page' => ['nullable', 'boolean'],
            'groups.*.multiple' => ['nullable', 'boolean'],
            'groups.*.display' => ['nullable', Rule::in(['auto', 'chips', 'dropdown'])],
            'unify' => ['nullable', 'boolean'],
        ]);

        $menuServiceId = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        $known = OptionGroup::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $now = now();
        $rows = [];

        foreach ($data['groups'] ?? [] as $groupId => $g) {
            if (! in_array((int) $groupId, $known, true) || empty($g['enabled'])) {
                continue;
            }
            $rows[] = [
                'platform_service_id' => $menuServiceId, 'option_group_id' => (int) $groupId,
                'child_id' => ServiceOptionGroupPlacement::ALL_CHILDREN, 'item_type_key' => '',
                'usage' => ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE, 'branches_as_sections' => 0, 'is_active' => 1,
                'sort_order' => (int) ($g['sort_order'] ?? 0),
                'show_on_page' => (bool) ($g['show_on_page'] ?? true),
                'display' => $g['display'] ?? 'auto',
                'multiple' => (bool) ($g['multiple'] ?? true),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        $folded = 0;
        DB::transaction(function () use ($menuServiceId, $rows, $request, &$folded) {
            DB::table('service_option_group_placements')
                ->where('platform_service_id', $menuServiceId)->where('usage', ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE)
                ->where('child_id', ServiceOptionGroupPlacement::ALL_CHILDREN)->delete();
            if ($rows !== []) {
                DB::table('service_option_group_placements')->insert($rows);
            }
            // A trade's ACTIVE copy of a common group is now redundant. Its inactive row
            // (the trade hiding the group) is a decision of its own and stays.
            if ($request->boolean('unify') && $rows !== []) {
                $folded = DB::table('service_option_group_placements')
                    ->where('platform_service_id', $menuServiceId)->where('usage', ServiceOptionGroupPlacement::USAGE_DESCRIPTIVE)
                    ->where('child_id', '>', 0)->where('is_active', 1)
                    ->whereIn('option_group_id', array_column($rows, 'option_group_id'))->delete();
            }
        });

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape', 'group_id', 'preview']))
            ->with('success', __('حُفظت الخيارات الوصفية العامة (:n مجموعة) — تظهر فى كل المنيوهات.', ['n' => count($rows)]) . ($folded ? ' ' . __('ودُمجت :n نسخة متفرقة من الأنشطة.', ['n' => $folded]) : ''));
    }

    /** POST — keep the shape being previewed for this group. */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('option_groups', 'id')->where('price_role', OptionGroup::ROLE_LINE)],
            'profile_id' => ['nullable', 'integer', Rule::exists('menu_detail_profiles', 'id')],
        ]);

        $group = OptionGroup::query()->findOrFail($data['group_id']);
        $group->update(['menu_detail_profile_id' => $data['profile_id'] ?? null]);

        $label = $group->menu_detail_profile_id
            ? __('منيو تفصيلي — :kind', ['kind' => MenuDetailProfile::query()->find($group->menu_detail_profile_id)?->label()])
            : __('منيو أساسي');

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape']) + ['group_id' => $group->id])
            ->with('success', __('«:group» أصبح :shape.', ['group' => $group->name_ar, 'shape' => $label]));
    }

    /** POST — the fields of one detail kind: which, in what order, on the card, as a filter. */
    public function saveFields(Request $request, MenuDetailProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*.enabled' => ['nullable', 'boolean'],
            'fields.*.show_on_card' => ['nullable', 'boolean'],
            'fields.*.per_item' => ['nullable', 'boolean'],
            'fields.*.is_filterable' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fields.*.show_on_page' => ['nullable', 'boolean'],
            'fields.*.display' => ['nullable', Rule::in(['auto', 'chips', 'dropdown'])],
        ]);

        $known = DB::table('catalog_attributes')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $now = now();
        $rows = [];

        foreach ($data['fields'] ?? [] as $attributeId => $field) {
            $attributeId = (int) $attributeId;
            if (! in_array($attributeId, $known, true) || empty($field['enabled'])) {
                continue;
            }
            $rows[] = [
                'menu_detail_profile_id' => $profile->id,
                'catalog_attribute_id' => $attributeId,
                'sort_order' => (int) ($field['sort_order'] ?? 0),
                'show_on_card' => ! empty($field['show_on_card']),
                'per_item' => ! empty($field['per_item']),
                'is_filterable' => ! empty($field['is_filterable']),
                'show_on_page' => (bool) ($field['show_on_page'] ?? true), // the form posts 0 for an unticked box; a caller that says nothing keeps it on
                'display' => $field['display'] ?? 'auto',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // The «يعتمد على كتالوج» switch lives in the same form now — one save for
        // the whole editor. Only read when the form says it carried the switch.
        if ($request->has('uses_catalog_form')) {
            $profile->update(['uses_catalog' => $request->boolean('uses_catalog')]);
        }

        DB::transaction(function () use ($profile, $rows) {
            DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $profile->id)->delete();
            if ($rows !== []) {
                DB::table('menu_detail_profile_attributes')->insert($rows);
            }

        });

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape', 'group_id']) + ['preview' => $profile->id])
            ->with('success', __('حُفظت حقول «:kind».', ['kind' => $profile->label()]));
    }

    /** POST — a new detail kind (e.g. «ألواح بديل الخشب»), fields picked afterwards. */
    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:40'],
            'uses_catalog' => ['nullable', 'boolean'],
        ]);

        $base = Str::slug((string) ($data['name_en'] ?? '')) ?: 'profile';
        $code = $base;
        for ($i = 2; MenuDetailProfile::query()->where('code', $code)->exists(); $i++) {
            $code = $base . '-' . $i;
        }

        $profile = MenuDetailProfile::query()->create([
            'code' => $code,
            'name_ar' => trim($data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'icon' => trim((string) ($data['icon'] ?? '')) ?: null,
            // A new kind has no catalog behind it unless the admin says so.
            'uses_catalog' => $request->boolean('uses_catalog'),
            'sort_order' => (int) MenuDetailProfile::query()->max('sort_order') + 10,
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape', 'group_id']) + ['preview' => $profile->id])
            ->with('success', __('أُضيف نوع التفاصيل «:kind» — اختر حقوله.', ['kind' => $profile->name_ar]));
    }

    /** POST — does this kind pick a real catalog model, or does the merchant name every item himself? */
    public function saveSettings(Request $request, MenuDetailProfile $profile): RedirectResponse
    {
        $profile->update(['uses_catalog' => $request->boolean('uses_catalog')]);

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape', 'group_id']) + ['preview' => $profile->id])
            ->with('success', $profile->uses_catalog
                ? __('«:kind» يعتمد الآن على كتالوج منتجات.', ['kind' => $profile->label()])
                : __('«:kind» بلا كتالوج — التاجر يسمّى الصنف ويدخل كل حقوله.', ['kind' => $profile->label()]));
    }

    /**
     * POST — «اريد زر اضافة حقل»: a brand-new field (a number, words, or a
     * choice from a list) made right here and switched on for this kind, so a
     * new kind never waits on a developer to invent its attributes.
     */
    public function storeAttribute(Request $request, MenuDetailProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'data_type' => ['required', Rule::in(['text', 'number', 'select'])],
            'unit' => ['nullable', 'string', 'max:40'],
            'options' => ['nullable', 'string', 'max:4000'],
        ]);

        $options = collect(preg_split('/\R/u', (string) ($data['options'] ?? '')) ?: [])
            ->map(fn ($o) => trim($o))->filter()->unique()->values();

        if ($data['data_type'] === 'select' && $options->isEmpty()) {
            return back()->withInput()->withErrors(['options' => __('اكتب خيارًا واحدًا على الأقل لحقل القائمة.')]);
        }

        $nameAr = trim($data['name_ar']);
        $nameEn = trim((string) ($data['name_en'] ?? '')) ?: null;

        // The same field twice is one field: switch it on instead of minting a duplicate.
        $existing = DB::table('catalog_attributes')->where('name_ar', $nameAr)->first();
        $attributeId = 0;

        DB::transaction(function () use ($profile, $data, $nameAr, $nameEn, $options, $existing, &$attributeId) {
            $now = now();

            if ($existing) {
                $attributeId = (int) $existing->id;
            } else {
                $unitId = null;
                $unit = trim((string) ($data['unit'] ?? ''));
                if ($unit !== '') {
                    $unitId = DB::table('catalog_units')->where('name_ar', $unit)->orWhere('name_en', $unit)->value('id');
                    $unitId ??= DB::table('catalog_units')->insertGetId([
                        'code' => 'u-' . Str::lower(Str::random(8)), 'name_ar' => $unit, 'name_en' => $unit,
                        'unit_type' => 'other', 'is_active' => 1, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                $code = Str::slug((string) $nameEn) ?: 'field';
                $base = $code;
                for ($i = 2; DB::table('catalog_attributes')->where('code', $code)->exists(); $i++) {
                    $code = $base . '-' . $i;
                }

                $attributeId = (int) DB::table('catalog_attributes')->insertGetId([
                    'code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'data_type' => $data['data_type'],
                    'unit_id' => $unitId, 'is_filterable' => 1, 'is_variant_axis' => 0, 'is_required' => 0,
                    'sort_order' => (int) DB::table('catalog_attributes')->max('sort_order') + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                foreach ($options as $i => $label) {
                    DB::table('catalog_attribute_options')->insert([
                        'attribute_id' => $attributeId, 'slug' => (Str::slug($label) ?: 'o') . '-' . ($i + 1),
                        'value_ar' => $label, 'value_en' => $label, 'sort_order' => ($i + 1) * 10, 'is_active' => 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            DB::table('menu_detail_profile_attributes')->updateOrInsert(
                ['menu_detail_profile_id' => $profile->id, 'catalog_attribute_id' => $attributeId],
                [
                    'sort_order' => (int) DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $profile->id)->max('sort_order') + 10,
                    'show_on_card' => false, 'per_item' => ! $profile->uses_catalog, 'is_filterable' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        });

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape', 'group_id']) + ['preview' => $profile->id])
            ->with('success', __('أُضيف الحقل «:name» إلى «:kind».', ['name' => $nameAr, 'kind' => $profile->label()]));
    }

    /**
     * A real catalog product filed under one of the group's branches, with its
     * values for the profile's fields — so the phone shows «Galaxy S24 Ultra ·
     * 12GB · 256GB», not placeholders. Null when the group has no product yet.
     *
     * @param  list<int>  $branchIds
     * @return array{branch_id:int,name:string,brand:?string,image:?string,values:array<string,string>}|null
     */
    private function sampleProduct(array $branchIds, MenuDetailProfile $profile): ?array
    {
        if ($branchIds === []) {
            return null;
        }

        $product = DB::table('catalog_products as p')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->whereIn('p.line_option_id', $branchIds)
            ->whereNull('p.deleted_at')
            ->where('p.is_active', 1)
            ->where('p.approval_status', 'approved')
            // The group's FIRST branch that has products, so the preview opens
            // where a merchant would (موبايل before تابلت).
            ->orderByRaw('FIELD(p.line_option_id, ' . implode(',', array_map('intval', $branchIds)) . ')')
            ->orderByDesc('p.id')
            ->first(['p.id', 'p.line_option_id', 'p.name_ar', 'p.name_en', 'p.main_image', 'b.name_ar as brand']);

        if (! $product) {
            return null;
        }

        $values = collect(app(ProductSpecs::class)->forProducts([(int) $product->id])[(int) $product->id] ?? [])
            ->mapWithKeys(fn ($row) => [(string) ($row['code'] ?? '') => (string) ($row['value'] ?? '')])
            ->all();

        return [
            'branch_id' => (int) $product->line_option_id,
            'name' => (string) ($product->name_ar ?: $product->name_en),
            'brand' => $product->brand,
            'image' => $product->main_image,
            'values' => $values,
        ];
    }
}
