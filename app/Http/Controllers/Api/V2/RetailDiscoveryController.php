<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Retail\RetailListingVisibility;
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
 *
 * ── And only listings this viewer may SEE ───────────────────────────────────
 *
 * A manufacturer's wholesale list is addressed to named buyers
 * ({@see RetailListingVisibility}), and the restriction is applied to every
 * query in this file — the three facet rollups, the product index and the
 * per-product offer list.
 *
 * Applied to the QUERY and not to the results, which is the whole reason it is
 * five identical lines rather than one filter at the end: these queries GROUP.
 * Filtering rows afterwards would leave «١٢ منتجًا» printed over a page of
 * four, and a wrong count is worse than a missing row.
 */
final class RetailDiscoveryController extends Controller
{
    public function __construct(private readonly RetailListingVisibility $visibility)
    {
    }

    /**
     * Who is asking.
     *
     * Retail discovery is a PUBLIC surface — a guest browses it — so there is
     * no auth middleware to lean on and `$request->user()` is null more often
     * than not. Null means the shelf and nothing else.
     */
    private function viewer(Request $request): ?User
    {
        /*
         * `$request->user()` alone is not enough here and the reason is easy to
         * miss: this route carries no `auth:sanctum`, so the default guard is
         * `web` and a perfectly valid bearer token reads as a guest. The named
         * company would have been shown nothing — the restriction working
         * against the one business it was written for.
         *
         * Same fallback CommentController and PostController use.
         */
        $user = $request->user() ?: auth('sanctum')->user();

        return $user instanceof User ? $user : null;
    }
    /** Product categories and brands that actually have active listings. */
    public function filters(Request $request)
    {
        // Narrow every facet count to shops open right now, when asked.
        $openNow = $request->boolean('open_now');
        $hours = app(\App\Services\BusinessHoursService::class);
        $openScope = fn ($q) => $openNow ? $hours->applyOpenNow($q, 'l.business_id') : $q;

        // …and never count a listing this viewer cannot open.
        $viewer = $this->viewer($request);
        $mine = fn ($q) => $this->visibility->apply($q, $viewer, 'l');

        // Branch-level (product_categories == retail branches) rollup.
        $branches = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->join('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->where('l.is_active', 1)
            ->whereNull('p.deleted_at')
            ->tap($mine)
            ->tap($openScope)
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
            ->tap($mine)
            ->tap($openScope)
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
            ->tap($mine)
            ->tap($openScope)
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
            'open_now' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categoryId = (int) ($data['category_id'] ?? 0);
        $childId = (int) ($data['child_id'] ?? 0);
        $brandId = (int) ($data['brand_id'] ?? 0);
        $q = trim((string) ($data['q'] ?? ''));

        // Only count listings from shops open right now, when asked — so a
        // product surfaces only if an open shop actually sells it.
        $offers = DB::table('business_catalog_listings')
            ->where('is_active', 1)
            ->tap(fn ($qq) => $this->visibility->apply($qq, $this->viewer($request), 'business_catalog_listings'))
            ->when(
                $request->boolean('open_now'),
                fn ($qq) => app(\App\Services\BusinessHoursService::class)
                    ->applyOpenNow($qq, 'business_catalog_listings.business_id')
            )
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
    public function show(Request $request, int $product)
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
            ->tap(fn ($qq) => $this->visibility->apply($qq, $this->viewer($request), 'l'))
            ->orderBy('l.price')
            ->orderBy('u.name')
            ->get([
                'l.id', 'l.price', 'l.currency', 'l.stock', 'l.sku',
                'u.id as business_id', 'u.name as business_name_ar', 'u.name_en as business_name_en', 'u.logo as business_logo',
            ])
            ->map(fn ($o) => [
                'listing_id' => (int) $o->id,
                'business' => [
                    'id' => (int) $o->business_id,
                    'name' => $this->label($o->business_name_ar, $o->business_name_en, ''),
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

    /**
     * GET /discovery/retail/listings — one card per LISTING (business +
     * product + price), the feed behind the Categories screen's "Retail"
     * service chip. Deliberately per-listing rather than per-business (the
     * generic DiscoveryController::recommended() shape) or per-product (the
     * `products()` shape above): a customer choosing "Retail" wants to see
     * what's actually for sale and at what price, not rediscover the same
     * business/product hop this controller already offers elsewhere.
     *
     * `category_id` narrows to sellers under that ROOT category (e.g.
     * "Factories" vs "Shops") — the same axis
     * DiscoveryController::recommended() already supports for every other
     * service, generalised here since retail sellers span very different
     * trades (a furniture factory and a greengrocer both "do retail").
     */
    public function listings(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categoryId = (int) ($data['category_id'] ?? 0);
        $q = trim((string) ($data['q'] ?? ''));

        $ratingExpr = 'COALESCE(ROUND(uor.review_stars_sum / GREATEST(uor.review_count, 1), 2), 0)';

        $rows = DB::table('business_catalog_listings as l')
            ->join('users as u', 'u.id', '=', 'l.business_id')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->leftJoin('user_operation_ratings as uor', function ($join) {
                $join->on('uor.user_id', '=', 'u.id')->where('uor.role', 'business');
            })
            ->where('l.is_active', 1)
            ->where('u.type', 'business')
            ->whereNull('p.deleted_at')
            ->tap(fn ($qq) => $this->visibility->apply($qq, $this->viewer($request), 'l'))
            ->when($categoryId > 0, fn ($qq) => $qq->where('u.category_id', $categoryId))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $qq->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(p.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(p.name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(u.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(u.name_en) LIKE ?', [$like]);
                });
            })
            ->orderByRaw("$ratingExpr DESC")
            ->orderByDesc('l.id')
            ->select(
                'l.id as listing_id', 'l.price', 'l.currency', 'l.stock',
                'p.id as product_id', 'p.name_ar as product_name_ar', 'p.name_en as product_name_en',
                'p.main_image as product_image',
                'u.id as business_id', 'u.name as business_name_ar', 'u.name_en as business_name_en',
                'u.logo as business_logo', 'u.category_id', 'u.category_child_id'
            )
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        $businessIds = $rows->getCollection()->pluck('business_id')->unique()->map(fn ($id) => (int) $id)->all();
        $openNow = app(\App\Services\BusinessHoursService::class)->openNowMap($businessIds);

        $rows->getCollection()->transform(fn ($r) => [
            'listing_id' => (int) $r->listing_id,
            'price' => (float) $r->price,
            'currency' => $r->currency ?: 'EGP',
            'stock' => $r->stock !== null ? (int) $r->stock : null,
            'product' => [
                'id' => (int) $r->product_id,
                'name' => $this->label($r->product_name_ar, $r->product_name_en, __('منتج #') . $r->product_id),
                'image' => $r->product_image,
            ],
            'business' => [
                'id' => (int) $r->business_id,
                'name' => $this->label($r->business_name_ar, $r->business_name_en, ''),
                'logo' => $r->business_logo,
                'category_id' => $r->category_id ? (int) $r->category_id : null,
                'category_child_id' => $r->category_child_id ? (int) $r->category_child_id : null,
                'is_open_now' => $openNow[(int) $r->business_id] ?? true,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => ['category_id' => $categoryId ?: null, 'q' => $q ?: null],
                'listings' => $rows,
            ],
        ]);
    }

    /**
     * GET /discovery/retail/business/{business} — one seller's WHOLE retail
     * shelf, for the storefront a listing card opens into (see listings()
     * above) — the customer lands here having tapped one product, then sees
     * everything else this seller carries in the same place, exactly like
     * how the merchant's own "My Products" screen shows it to them.
     *
     * Deliberately its OWN endpoint rather than reusing
     * BusinessOfferingsController::show() — that one's `OfferingDiscovery`
     * only ever returns `menu`/`price` sourced rows
     * (`app/Services/OfferingDiscovery.php`), never `business_catalog_listings`
     * at all, so it cannot answer "what does this seller carry at retail".
     */
    public function business(Request $request, int $business)
    {
        $biz = User::query()->where('type', 'business')
            ->find($business, ['id', 'name', 'name_en', 'logo']);

        if (! $biz) {
            return response()->json(['success' => false, 'message' => __('النشاط غير موجود.')], 404);
        }

        $listings = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->where('l.business_id', $business)
            ->where('l.is_active', 1)
            ->whereNull('p.deleted_at')
            ->tap(fn ($qq) => $this->visibility->apply($qq, $this->viewer($request), 'l'))
            ->orderBy('p.name_ar')
            ->orderBy('l.id')
            ->get([
                'l.id as listing_id', 'l.price', 'l.currency', 'l.stock',
                'p.id as product_id', 'p.name_ar as product_name_ar', 'p.name_en as product_name_en',
                'p.main_image as product_image',
            ])
            ->map(fn ($r) => [
                'listing_id' => (int) $r->listing_id,
                'price' => (float) $r->price,
                'currency' => $r->currency ?: 'EGP',
                'stock' => $r->stock !== null ? (int) $r->stock : null,
                'product' => [
                    'id' => (int) $r->product_id,
                    'name' => $this->label($r->product_name_ar, $r->product_name_en, __('منتج #') . $r->product_id),
                    'image' => $r->product_image,
                ],
            ])->values();

        $minOrderAmount = \App\Models\BusinessRetailSetting::query()
            ->where('business_id', $biz->id)->value('min_order_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [
                    'id' => (int) $biz->id,
                    'name' => $this->label($biz->name, $biz->name_en, ''),
                    'logo' => $biz->logo,
                    // null = the seller imposes no minimum. Shown up front
                    // here rather than only surfacing at the checkout-time
                    // 422 — see CustomerCartService::assertMeetsRetailMinimum().
                    'min_order_amount' => $minOrderAmount !== null ? (float) $minOrderAmount : null,
                ],
                'listings' => $listings,
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
