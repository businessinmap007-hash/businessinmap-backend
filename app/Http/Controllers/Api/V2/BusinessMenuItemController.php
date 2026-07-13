<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\MenuItemResource;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * v2 business menu management — the business role edits its own menu from the
 * app (mirrors the web Business\MenuItem/Variant/Extra controllers, which had
 * no API). Every row is scoped to business_id = the authenticated user.
 */
final class BusinessMenuItemController extends Controller
{
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
        $model->load(['variants' => fn ($q) => $q->orderBy('id'), 'extras' => fn ($q) => $q->orderBy('id')]);

        return (new MenuItemResource($model))->additional(['success' => true]);
    }

    /** POST /api/v2/business/menu/items */
    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $item = MenuItem::create($this->validatedItem($request, $businessId) + ['business_id' => $businessId]);

        return (new MenuItemResource($item))->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item} */
    public function update(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $model->update($this->validatedItem($request, $this->businessId($request)));

        return (new MenuItemResource($model->fresh()))->additional(['success' => true]);
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

    // ─────────────────────────── Extras ───────────────────────────

    /** POST /api/v2/business/menu/items/{item}/extras */
    public function storeExtra(Request $request, int $item)
    {
        $model = $this->ownItem($request, $item);
        $extra = $model->extras()->create($this->validatedExtra($request));

        return response()->json(['success' => true, 'data' => ['id' => (int) $extra->id]], 201);
    }

    /** PUT/PATCH /api/v2/business/menu/items/{item}/extras/{extra} */
    public function updateExtra(Request $request, int $item, int $extra)
    {
        $model = $this->ownItem($request, $item);
        MenuItemExtra::query()->where('menu_item_id', $model->id)->findOrFail($extra)
            ->update($this->validatedExtra($request));

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

    private function businessId(Request $request): int
    {
        $user = $request->user();
        if (! $user->isBusiness()) {
            abort(403, 'إدارة المنيو متاحة لحسابات الأعمال فقط.');
        }

        return (int) $user->id;
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
    private function validatedExtra(Request $request): array
    {
        $data = $request->validate([
            'group_key' => ['nullable', 'string', 'max:50'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'group_key' => trim((string) ($data['group_key'] ?? '')) ?: null,
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'price' => round((float) $data['price'], 2),
            'max_qty' => (int) ($data['max_qty'] ?? 1),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
