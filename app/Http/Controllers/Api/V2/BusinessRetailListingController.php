<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\V2\BusinessRetailListingResource;
use App\Models\BusinessCatalogListing;
use App\Services\Retail\ListingAudienceWriter;
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
            // Products already on the shelf stay out of the picker — a further
            // condition/payment ROW for one is added by posting its product id
            // again with the other variant, not by picking it anew.
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

    /**
     * GET /api/v2/business/retail-listings/variant-options — the condition
     * (جديد/مستعمل/…) and payment (كاش/تقسيط) choices THIS business's child
     * carries, for the add/edit form's two pickers.
     */
    public function variantOptions()
    {
        $options = app(\App\Services\Catalog\RetailPriceVariants::class)->optionsFor($this->childId(), $this->rootId());
        $shape = fn ($rows) => $rows->map(fn ($o) => ['id' => (int) $o->id, 'name' => $this->localize($o->name_ar, $o->name_en)])->values();

        return response()->json(['success' => true, 'data' => [
            'conditions' => $shape($options['condition']),
            'payments' => $shape($options['payment']),
        ]]);
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
            ->where('condition_option_id', $data['condition_option_id'])
            ->where('payment_option_id', $data['payment_option_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'catalog_product_id' => [__('هذا المنتج مضاف بالفعل في منتجاتك بنفس الحالة وطريقة الدفع.')],
            ]);
        }

        $audiences = app(ListingAudienceWriter::class);

        $row = DB::transaction(function () use ($data, $request, $audiences) {
            $listing = BusinessCatalogListing::create(
                $data
                + $audiences->columns($request, $this->businessId(), (int) $data['catalog_product_id'])
                + ['business_id' => $this->businessId()]
            );

            $audiences->sync($listing, $request);

            return $listing;
        });

        return (new BusinessRetailListingResource($row->load('product:id,name_ar,name_en,main_image,default_barcode')))
            ->additional(['success' => true])->response()->setStatusCode(201);
    }

    /** PUT/PATCH /api/v2/business/retail-listings/{listing} */
    public function update(Request $request, int $listing)
    {
        $row = $this->scoped($listing);
        $audiences = app(ListingAudienceWriter::class);

        $data = $this->validatedData($request, false, $row);

        $clash = BusinessCatalogListing::query()
            ->where('business_id', $this->businessId())
            ->where('catalog_product_id', $row->catalog_product_id)
            ->where('condition_option_id', $data['condition_option_id'])
            ->where('payment_option_id', $data['payment_option_id'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'condition_option_id' => [__('يوجد بالفعل سطر لهذا المنتج بنفس الحالة وطريقة الدفع.')],
            ]);
        }

        DB::transaction(function () use ($row, $request, $audiences, $data) {
            $row->update(
                $data
                + $audiences->columns($request, $this->businessId(), (int) $row->catalog_product_id)
            );

            $audiences->sync($row->refresh(), $request);
        });

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
    private function validatedData(Request $request, bool $withProduct, ?BusinessCatalogListing $current = null): array
    {
        $rules = [
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'max_order_qty' => ['nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:20'],
            'sku' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            // The two price dimensions — a product may be listed several
            // times, once per condition / payment combination, each a full row.
            'condition_option_id' => ['nullable', 'integer', 'min:0'],
            'payment_option_id' => ['nullable', 'integer', 'min:0'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
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

        app(ListingAudienceWriter::class)->normalise($request);

        $rules += app(ListingAudienceWriter::class)->rules();

        $data = $request->validate($rules);

        $out = [
            'price' => round((float) $data['price'], 2),
            'stock' => isset($data['stock']) && $data['stock'] !== null ? max(0, (int) $data['stock']) : null,
            'min_order_qty' => isset($data['min_order_qty']) && $data['min_order_qty'] !== null ? (int) $data['min_order_qty'] : null,
            'max_order_qty' => isset($data['max_order_qty']) && $data['max_order_qty'] !== null ? (int) $data['max_order_qty'] : null,
            'unit' => trim((string) ($data['unit'] ?? '')) ?: null,
            'sku' => trim((string) ($data['sku'] ?? '')) ?: null,
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'EGP'))) ?: 'EGP',
            'is_active' => (int) $request->boolean('is_active', true),
        ];

        // Checked against the RESOLVED values (this form always replaces
        // both, so a request touching only one still has the other's
        // current-or-null value here) rather than a `gte:min_order_qty`
        // request rule, which breaks the moment a request sends one bound
        // without the other.
        if ($out['min_order_qty'] !== null && $out['max_order_qty'] !== null && $out['max_order_qty'] < $out['min_order_qty']) {
            throw ValidationException::withMessages([
                'max_order_qty' => [__('الحد الأقصى يجب أن يكون أكبر من أو يساوي الحد الأدنى.')],
            ]);
        }

        // Only options this business's own child carries in the two dimension
        // groups; anything else is refused rather than silently dropped.
        $allowed = app(\App\Services\Catalog\RetailPriceVariants::class)->optionsFor($this->childId(), $this->rootId());

        foreach (['condition' => 'condition_option_id', 'payment' => 'payment_option_id'] as $dimension => $field) {
            $id = array_key_exists($field, $data) && $data[$field] !== null
                ? (int) $data[$field]
                : (int) ($current->{$field} ?? 0);

            if ($id > 0 && ! $allowed[$dimension]->contains('id', $id)) {
                throw ValidationException::withMessages([
                    $field => [__('هذا الخيار غير متاح لنشاطك.')],
                ]);
            }

            $out[$field] = $id;
        }

        foreach (['description_ar', 'description_en'] as $field) {
            $out[$field] = array_key_exists($field, $data)
                ? (trim((string) $data[$field]) ?: null)
                : ($current->{$field} ?? null);
        }

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
