<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
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

        return view('admin-v2.menu-shapes.index', [
            'profiles' => $profiles,
            'fields' => $fields,
            'groups' => $groups,
            'group' => $group,
            'branches' => $branches,
            'preview' => $preview,
            'previewProfile' => $previewProfile,
            'sample' => $previewProfile && $group ? $this->sampleProduct($branches->pluck('id')->all(), $previewProfile) : null,
            'attributes' => DB::table('catalog_attributes as a')
                ->leftJoin('catalog_units as u', 'u.id', '=', 'a.unit_id')
                ->orderBy('a.sort_order')->orderBy('a.id')
                ->get(['a.id', 'a.code', 'a.name_ar', 'a.data_type', 'u.name_ar as unit']),
            'search' => $search,
            'shape' => $shape,
        ]);
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
            'fields.*.is_filterable' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
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
                'is_filterable' => ! empty($field['is_filterable']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
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
            'sort_order' => (int) MenuDetailProfile::query()->max('sort_order') + 10,
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.menu-shapes.index', $request->only(['q', 'shape', 'group_id']) + ['preview' => $profile->id])
            ->with('success', __('أُضيف نوع التفاصيل «:kind» — اختر حقوله.', ['kind' => $profile->name_ar]));
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
