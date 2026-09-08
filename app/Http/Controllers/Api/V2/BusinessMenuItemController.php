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
use App\Services\Media\ImageUploadService;
use App\Services\Menu\MenuSectionFromOptionGroup;
use App\Services\MerchantOfferingVocabulary;
use App\Support\SaleUnits;
use Illuminate\Http\Request;
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

        $shape = fn ($grouped) => collect($grouped)->map(fn ($options, $groupName) => [
            'group_id' => (int) $options->first()->group_id,
            'group_name' => (string) $groupName,
            // "ماركات الموبيلات", "ماركات السيارات", "ماركات الأجهزة
            // الكهربائية"... — every brand vocabulary on the platform is
            // named this way, so the item form can single one out as ITS
            // OWN dropdown instead of just another modifier chip: a
            // merchant chooses the brand, not just qualifies with it.
            'is_brand' => str_contains((string) $groupName, 'ماركات') || str_contains((string) $groupName, 'العلامة التجارية'),
            'options' => collect($options)->map(fn ($o) => [
                'id' => (int) $o->id,
                'name_ar' => $o->name_ar,
                'name_en' => $o->name_en,
            ])->values(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'lines' => $shape($vocabulary['lines']),
                'modifiers' => $shape($vocabulary['modifiers']),
            ],
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
        $item = MenuItem::create($data + ['business_id' => $businessId]);

        if (! $item->medicine_id) {
            $this->applyVocabulary($request, $item, explicitSection: $data['menu_section_id'] !== null);
        }

        return (new MenuItemResource($item->fresh()))->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item} */
    public function update(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $data = $this->validatedItem($request, $this->businessId($request));
        $model->update($data);

        if (! $model->medicine_id) {
            $this->applyVocabulary($request, $model, explicitSection: $data['menu_section_id'] !== null);
        }

        return (new MenuItemResource($model->fresh()))->additional(['success' => true]);
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
     * business never types "أنواع الأجهزة الكهربائية" itself.
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

        if ($line && ! $explicitSection) {
            $lineOption = $item->lineOption();
            $section = $lineOption ? $this->sectionFromGroup->resolve($businessId, $lineOption) : null;

            if ($section && (int) $item->menu_section_id !== (int) $section->id) {
                $item->forceFill(['menu_section_id' => $section->id])->saveQuietly();
            }
        }
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
        ]);

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
                // Not evidence: a menu photo is a picture of the goods, and
                // requiring a live camera would stop a merchant using the shots
                // he already has. `camera` stays reserved for proof.
                'source' => Image::SOURCE_UPLOAD,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => ['images' => array_map(
                fn (Image $image) => ['id' => (int) $image->id, 'image' => $image->image],
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
            'brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => $request->boolean('is_active', true),
        ];
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
