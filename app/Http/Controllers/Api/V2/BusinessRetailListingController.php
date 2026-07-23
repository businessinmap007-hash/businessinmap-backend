<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\BusinessRetailListingResource;
use App\Models\BusinessCatalogListing;
use App\Models\CatalogProduct;
use App\Models\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 retail merchant listings — a business lists products it sells over the
 * shared catalog master, from the app (mirrors the web
 * Business\CatalogListingController, which had no API). A listing may only point
 * at an active master WITHIN the owner's retail scope (the item types the
 * owner's category child offers under the retail service), and one product is
 * listed at most once per business. The business-only gate is the `business`
 * middleware; retail must additionally be offered by the owner's subcategory.
 */
final class BusinessRetailListingController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/retail-listings */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string) ($data['q'] ?? ''));

        $rows = BusinessCatalogListing::query()
            ->with(['product:id,name_ar,name_en,main_image,default_barcode'])
            ->where('business_id', $this->businessId())
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->whereHas('product', fn ($p) => $p
                    ->whereRaw('LOWER(name_ar) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name_en) LIKE ?', [$like]));
            })
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return BusinessRetailListingResource::collection($rows)->additional(['success' => true]);
    }

    /**
     * GET /api/v2/business/retail-listings/lookup?q=
     * Search the shared catalog master for products this business hasn't listed
     * yet, scoped to the retail item types the owner's category child offers —
     * the picker feed for the create form. Mirrors the web productLookup.
     */
    public function lookup(Request $request)
    {
        $scope = $this->retailScope();

        if ($scope === null) {
            return response()->json(['success' => false, 'data' => ['items' => []], 'message' => __('خدمة التجزئة غير متاحة لنشاطك.')], 403);
        }

        if (empty($scope)) {
            return response()->json(['success' => true, 'data' => ['items' => []]]);
        }

        $term = trim((string) $request->get('q', ''));

        $items = CatalogProduct::query()
            ->active()
            ->search($term)
            ->whereIn('catalog_products.product_category_child_id', $scope)
            ->whereNotIn('catalog_products.id', function ($sub) {
                $sub->from('business_catalog_listings')
                    ->select('catalog_product_id')
                    ->where('business_id', $this->businessId());
            })
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'catalog_products.brand_id')
            ->orderBy('catalog_products.name_ar')
            ->limit(20)
            ->get([
                'catalog_products.id', 'catalog_products.name_ar', 'catalog_products.name_en',
                'catalog_products.default_barcode', 'catalog_products.main_image', 'b.name_ar as brand_name',
            ])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => $this->localize($p->name_ar, $p->name_en),
                'brand' => (string) ($p->brand_name ?? ''),
                'barcode' => (string) ($p->default_barcode ?? ''),
                'image' => (string) ($p->main_image ?? ''),
            ]);

        return response()->json(['success' => true, 'data' => ['items' => $items]]);
    }

    /** GET /api/v2/business/retail-listings/{listing} */
    public function show(int $listing)
    {
        $row = $this->scoped($listing)->load('product:id,name_ar,name_en,main_image,default_barcode');

        return (new BusinessRetailListingResource($row))->additional(['success' => true]);
    }

    /** POST /api/v2/business/retail-listings */
    public function store(Request $request)
    {
        if ($this->retailScope() === null) {
            return response()->json(['success' => false, 'message' => __('خدمة التجزئة غير متاحة لنشاطك.')], 403);
        }

        $data = $this->validatedData($request, true);

        $exists = BusinessCatalogListing::query()
            ->where('business_id', $this->businessId())
            ->where('catalog_product_id', $data['catalog_product_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'catalog_product_id' => [__('هذا المنتج مضاف بالفعل في منتجاتك.')],
            ]);
        }

        $row = BusinessCatalogListing::create($data + ['business_id' => $this->businessId()]);

        return (new BusinessRetailListingResource($row->load('product:id,name_ar,name_en,main_image,default_barcode')))
            ->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/retail-listings/{listing} */
    public function update(Request $request, int $listing)
    {
        $row = $this->scoped($listing);
        $row->update($this->validatedData($request, false));

        return (new BusinessRetailListingResource($row->fresh()->load('product:id,name_ar,name_en,main_image,default_barcode')))
            ->additional(['success' => true]);
    }

    /** DELETE /api/v2/business/retail-listings/{listing} */
    public function destroy(int $listing)
    {
        $this->scoped($listing)->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function scoped(int $id): BusinessCatalogListing
    {
        return BusinessCatalogListing::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    /**
     * The product_category_children ids the owner may list under retail: null =
     * the owner doesn't offer retail at all, [] = offers it but has no allowed
     * types. Mirrors the web CatalogListingController::retailScope.
     *
     * @return array<int>|null
     */
    private function retailScope(): ?array
    {
        $services = $this->servicesForChild();
        $retail = $services->firstWhere('key', PlatformService::KEY_RETAIL);

        if (! $retail) {
            return null;
        }

        $typeKeys = array_column($this->allowedTypesByService($services)[(int) $retail->id] ?? [], 'key');

        if (empty($typeKeys)) {
            return [];
        }

        return DB::table('product_category_children')
            ->whereIn('slug', $typeKeys)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<string,mixed> */
    private function validatedData(Request $request, bool $withProduct): array
    {
        $rules = [
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($withProduct) {
            // Only an active master WITHIN the owner's retail scope may be listed
            // — mirrors the lookup filter so a crafted id can't bypass the picker.
            $scope = $this->retailScope() ?: [-1];

            $rules['catalog_product_id'] = [
                'required', 'integer',
                Rule::exists('catalog_products', 'id')
                    ->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', 1)
                        ->whereIn('product_category_child_id', $scope)),
            ];
        }

        $data = $request->validate($rules);

        $out = [
            'price' => round((float) $data['price'], 2),
            'stock' => max(0, (int) ($data['stock'] ?? 0)),
            'sku' => trim((string) ($data['sku'] ?? '')) ?: null,
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'EGP'))) ?: 'EGP',
            'is_active' => (int) $request->boolean('is_active', true),
        ];

        if ($withProduct) {
            $out['catalog_product_id'] = (int) $data['catalog_product_id'];
        }

        return $out;
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
