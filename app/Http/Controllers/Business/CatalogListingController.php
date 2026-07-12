<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\CatalogProduct;
use App\Models\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My products" — retail listings for the business owner. The owner searches the
 * shared catalog master and lists a product with its own price + stock. Every
 * query is scoped to business_id = auth id.
 *
 * Catalog scope follows the retail branch taxonomy: the owner may only see/list
 * catalog products whose product_category_child slug is among the retail item
 * types allowed for their category child (config.allowed_item_types). The bridge
 * is the 1:1 mirror — product_category_children.slug == retail item-type key.
 * See docs/retail-branches-taxonomy.md.
 */
class CatalogListingController extends Controller
{
    use ResolvesOwnerCatalog;

    /**
     * The product_category_children ids the owner's child may list from, or null
     * when the child has no active retail service (screen is blocked entirely).
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

    private function scoped(int $id): BusinessCatalogListing
    {
        return BusinessCatalogListing::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $rows = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->where('l.business_id', $this->businessId())
            ->when($q !== '', fn ($query) => $query->whereRaw('LOWER(p.name_ar) LIKE ?', ['%' . mb_strtolower($q) . '%']))
            ->orderByDesc('l.id')
            ->select([
                'l.id', 'l.price', 'l.currency', 'l.stock', 'l.is_active', 'l.sku',
                'p.name_ar as product_name', 'p.main_image', 'p.default_barcode',
                'b.name_ar as brand_name',
            ])
            ->paginate(50)
            ->withQueryString();

        return view('business.catalog-listings.index', ['rows' => $rows, 'q' => $q]);
    }

    public function create(): View|RedirectResponse
    {
        if ($this->retailScope() === null) {
            return redirect()->route('business.products.index')
                ->with('error', 'خدمة التجزئة غير متاحة لنشاطك.');
        }

        return view('business.catalog-listings.create');
    }

    /**
     * Search the shared catalog master for products this business hasn't listed
     * yet (ajax, for the add-product picker). Scoped to the retail item types
     * allowed for the owner's category child.
     */
    public function productLookup(Request $request): JsonResponse
    {
        $scope = $this->retailScope();

        if ($scope === null) {
            return response()->json(['ok' => false, 'items' => []], 403);
        }

        if (empty($scope)) {
            return response()->json(['ok' => true, 'items' => []]);
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
                'catalog_products.id', 'catalog_products.name_ar', 'catalog_products.default_barcode',
                'catalog_products.main_image', 'b.name_ar as brand_name',
            ])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name_ar,
                'brand' => (string) ($p->brand_name ?? ''),
                'barcode' => (string) ($p->default_barcode ?? ''),
                'image' => (string) ($p->main_image ?? ''),
            ]);

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->retailScope() === null) {
            abort(403, 'خدمة التجزئة غير متاحة لنشاطك.');
        }

        $data = $this->validateData($request, true);

        $exists = BusinessCatalogListing::query()
            ->where('business_id', $this->businessId())
            ->where('catalog_product_id', $data['catalog_product_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'catalog_product_id' => 'هذا المنتج مضاف بالفعل في منتجاتك.',
            ]);
        }

        BusinessCatalogListing::create($data + ['business_id' => $this->businessId()]);

        return redirect()->route('business.products.index')->with('success', 'تمت إضافة المنتج بنجاح.');
    }

    public function edit(int $id): View
    {
        $row = $this->scoped($id);
        $product = CatalogProduct::query()->find($row->catalog_product_id);

        return view('business.catalog-listings.edit', ['row' => $row, 'product' => $product]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->scoped($id)->update($this->validateData($request, false));

        return back()->with('success', 'تم تحديث المنتج بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scoped($id)->delete();

        return redirect()->route('business.products.index')->with('success', 'تم حذف المنتج بنجاح.');
    }

    protected function validateData(Request $request, bool $withProduct): array
    {
        $rules = [
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable'],
        ];

        if ($withProduct) {
            // Only an active catalog master WITHIN the owner's retail scope may
            // be listed — mirrors the productLookup filter so a crafted id can't
            // bypass the picker.
            $scope = $this->retailScope() ?: [-1];

            $rules['catalog_product_id'] = [
                'required', 'integer',
                Rule::exists('catalog_products', 'id')
                    ->where(fn ($q) => $q->whereNull('deleted_at')->where('is_active', 1)
                        ->whereIn('product_category_child_id', $scope)),
            ];
        }

        $data = $request->validate($rules, [], [
            'catalog_product_id' => 'المنتج',
            'price' => 'السعر',
            'stock' => 'المخزون',
        ]);

        $out = [
            'price' => round((float) $data['price'], 2),
            'stock' => max(0, (int) ($data['stock'] ?? 0)),
            'sku' => trim((string) ($data['sku'] ?? '')) ?: null,
            'is_active' => (int) $request->boolean('is_active'),
        ];

        if ($withProduct) {
            $out['catalog_product_id'] = (int) $data['catalog_product_id'];
        }

        return $out;
    }
}
