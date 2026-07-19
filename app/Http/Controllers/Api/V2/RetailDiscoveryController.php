<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer discovery for the RETAIL side of the offering layer: the same
 * "offer = filter = index" principle applied to `business_catalog_listings`.
 * A business's active listing of a deduped catalog master is what it offers
 * AND what the customer browses/filters by. Journey:
 *   browse products (filter by product category / brand / search) →
 *   open a product → see every business that sells it and at what price.
 *
 * Only products with at least one active listing surface (empty filters are
 * meaningless). Masters are always scoped to whereNull(deleted_at).
 */
final class RetailDiscoveryController extends Controller
{
    /** Product categories and brands that actually have active listings. */
    public function filters(Request $request)
    {
        // Branch-level (product_categories == retail branches) rollup.
        $branches = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->join('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->where('l.is_active', 1)
            ->whereNull('p.deleted_at')
            ->groupBy('pc.id', 'pc.name_ar', 'pc.name_en')
            ->selectRaw('pc.id, pc.name_ar, pc.name_en, COUNT(DISTINCT p.id) AS products')
            ->orderByDesc('products')
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => $this->label($c->name_ar, $c->name_en, __('فرع #') . $c->id),
                'products' => (int) $c->products,
            ])->values();

        $categories = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->join('product_category_children as c', 'c.id', '=', 'p.product_category_child_id')
            ->where('l.is_active', 1)
            ->whereNull('p.deleted_at')
            ->groupBy('c.id', 'c.name_ar', 'c.name_en')
            ->selectRaw('c.id, c.name_ar, c.name_en, COUNT(DISTINCT p.id) AS products')
            ->orderByDesc('products')
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => $this->label($c->name_ar, $c->name_en, __('قسم #') . $c->id),
                'products' => (int) $c->products,
            ])->values();

        $brands = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->join('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->where('l.is_active', 1)
            ->whereNull('p.deleted_at')
            ->groupBy('b.id', 'b.name_ar', 'b.name_en')
            ->selectRaw('b.id, b.name_ar, b.name_en, COUNT(DISTINCT p.id) AS products')
            ->orderByDesc('products')
            ->get()
            ->map(fn ($b) => [
                'id' => (int) $b->id,
                'name' => $this->label($b->name_ar, $b->name_en, __('علامة #') . $b->id),
                'products' => (int) $b->products,
            ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'branches' => $branches,
                'categories' => $categories,
                'brands' => $brands,
            ],
        ]);
    }

    /**
     * Browse products that at least one business sells. Each result carries a
     * price range and the number of businesses offering it.
     */
    public function products(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'child_id' => ['nullable', 'integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categoryId = (int) ($data['category_id'] ?? 0);
        $childId = (int) ($data['child_id'] ?? 0);
        $brandId = (int) ($data['brand_id'] ?? 0);
        $q = trim((string) ($data['q'] ?? ''));

        $offers = DB::table('business_catalog_listings')
            ->where('is_active', 1)
            ->groupBy('catalog_product_id')
            ->selectRaw('catalog_product_id, MIN(price) AS min_price, MAX(price) AS max_price, COUNT(DISTINCT business_id) AS businesses');

        $rows = DB::table('catalog_products as p')
            ->joinSub($offers, 'o', 'o.catalog_product_id', '=', 'p.id')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_category_children as c', 'c.id', '=', 'p.product_category_child_id')
            ->whereNull('p.deleted_at')
            ->when($categoryId > 0, fn ($query) => $query->where('p.product_category_id', $categoryId))
            ->when($childId > 0, fn ($query) => $query->where('p.product_category_child_id', $childId))
            ->when($brandId > 0, fn ($query) => $query->where('p.brand_id', $brandId))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(p.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(p.name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(p.default_barcode) LIKE ?', [$like]);
                });
            })
            ->orderByDesc('o.businesses')
            ->orderBy('p.name_ar')
            ->orderBy('p.id')
            ->select(
                'p.id', 'p.name_ar', 'p.name_en', 'p.main_image', 'p.default_barcode',
                'p.package_value', 'p.package_label_ar', 'p.package_label_en',
                'b.name_ar as brand_name_ar', 'b.name_en as brand_name_en',
                'c.id as child_id', 'c.name_ar as child_name_ar', 'c.name_en as child_name_en',
                'o.min_price', 'o.max_price', 'o.businesses'
            )
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        $rows->getCollection()->transform(fn ($p) => [
            'id' => (int) $p->id,
            'name' => $this->label($p->name_ar, $p->name_en, __('منتج #') . $p->id),
            'image' => $p->main_image,
            'barcode' => $p->default_barcode,
            'package' => $this->package($p),
            'brand' => $this->label($p->brand_name_ar, $p->brand_name_en, ''),
            'category' => [
                'id' => $p->child_id ? (int) $p->child_id : null,
                'name' => $this->label($p->child_name_ar, $p->child_name_en, ''),
            ],
            'min_price' => (float) $p->min_price,
            'max_price' => (float) $p->max_price,
            'businesses' => (int) $p->businesses,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => [
                    'category_id' => $categoryId ?: null,
                    'child_id' => $childId ?: null,
                    'brand_id' => $brandId ?: null,
                    'q' => $q ?: null,
                ],
                'products' => $rows,
            ],
        ]);
    }

    /** One master product with every business that sells it, cheapest first. */
    public function show(int $product)
    {
        $p = DB::table('catalog_products as p')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_category_children as c', 'c.id', '=', 'p.product_category_child_id')
            ->whereNull('p.deleted_at')
            ->where('p.id', $product)
            ->select(
                'p.id', 'p.name_ar', 'p.name_en', 'p.main_image', 'p.default_barcode',
                'p.package_value', 'p.package_label_ar', 'p.package_label_en',
                'b.name_ar as brand_name_ar', 'b.name_en as brand_name_en',
                'c.id as child_id', 'c.name_ar as child_name_ar', 'c.name_en as child_name_en'
            )
            ->first();

        if (! $p) {
            return response()->json(['success' => false, 'message' => __('المنتج غير موجود.')], 404);
        }

        $offers = DB::table('business_catalog_listings as l')
            ->join('users as u', 'u.id', '=', 'l.business_id')
            ->where('l.catalog_product_id', $product)
            ->where('l.is_active', 1)
            ->orderBy('l.price')
            ->orderBy('u.name')
            ->get([
                'l.id', 'l.price', 'l.currency', 'l.stock', 'l.sku',
                'u.id as business_id', 'u.name as business_name', 'u.logo as business_logo',
            ])
            ->map(fn ($o) => [
                'listing_id' => (int) $o->id,
                'business' => [
                    'id' => (int) $o->business_id,
                    'name' => (string) $o->business_name,
                    'logo' => $o->business_logo,
                ],
                'price' => (float) $o->price,
                'currency' => $o->currency ?: 'EGP',
                'stock' => $o->stock !== null ? (int) $o->stock : null,
                'sku' => $o->sku,
            ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => (int) $p->id,
                    'name' => $this->label($p->name_ar, $p->name_en, __('منتج #') . $p->id),
                    'image' => $p->main_image,
                    'barcode' => $p->default_barcode,
                    'package' => $this->package($p),
                    'brand' => $this->label($p->brand_name_ar, $p->brand_name_en, ''),
                    'category' => [
                        'id' => $p->child_id ? (int) $p->child_id : null,
                        'name' => $this->label($p->child_name_ar, $p->child_name_en, ''),
                    ],
                ],
                'offers' => $offers,
            ],
        ]);
    }

    private function package($p): string
    {
        $value = $p->package_value !== null ? rtrim(rtrim((string) $p->package_value, '0'), '.') : '';
        $label = $this->label($p->package_label_ar, $p->package_label_en, '');

        return trim($value . ' ' . $label);
    }

    private function label($ar, $en, $fallback): string
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        $primary   = app()->getLocale() === 'en' ? $en : $ar;
        $secondary = app()->getLocale() === 'en' ? $ar : $en;

        return $primary !== '' ? $primary : ($secondary !== '' ? $secondary : (string) $fallback);
    }
}
