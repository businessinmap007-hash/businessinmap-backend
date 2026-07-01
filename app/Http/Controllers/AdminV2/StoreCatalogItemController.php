<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreCatalogItemController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $lookup = (string) $request->get('lookup', 'table');
            if ($lookup === 'products') return $this->productLookup($request);
            if ($lookup === 'businesses') return $this->businessLookup($request);
            return $this->table($request);
        }

        $businessId = (int) $request->get('business_id', 0);
        $childId = (int) $request->get('child_id', 0);
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $businesses = DB::table('users')
            ->select('id', 'name')
            ->where('type', 'business')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $children = DB::table('product_category_children')
            ->select('id', 'name_ar', 'name_en')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = $this->baseRowsQuery($businessId, $childId, $q, $status)
            ->orderByDesc('bcp.id')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'total' => DB::table('business_catalog_products')->count(),
            'active' => DB::table('business_catalog_products')->where('status', 'active')->count(),
            'available' => DB::table('business_catalog_products')->where('is_available', 1)->count(),
            'out' => DB::table('business_catalog_products')->where('stock_status', 'out_of_stock')->count(),
        ];

        return view('admin-v2.store-catalog-items.index', compact('rows', 'businesses', 'children', 'stats', 'businessId', 'childId', 'q', 'status'));
    }

    protected function table(Request $request)
    {
        $businessId = (int) $request->get('business_id', 0);
        $childId = (int) $request->get('child_id', 0);
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $rows = $this->baseRowsQuery($businessId, $childId, $q, $status)
            ->orderByDesc('bcp.id')
            ->limit(80)
            ->get();

        return response()->json([
            'ok' => true,
            'count' => $rows->count(),
            'html' => view('admin-v2.store-catalog-items._rows', compact('rows'))->render(),
        ]);
    }

    protected function productLookup(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $childId = (int) $request->get('child_id', 0);

        $rows = DB::table('catalog_products as cp')
            ->leftJoin('product_category_children as pcc', 'pcc.id', '=', 'cp.product_category_child_id')
            ->leftJoin('catalog_brands as cb', 'cb.id', '=', 'cp.brand_id')
            ->select('cp.id', 'cp.bim_code', 'cp.name_ar', 'cp.name_en', 'cp.package_label_ar', 'cp.package_label_en', 'pcc.name_ar as child_name_ar', 'cb.name_ar as brand_name_ar')
            ->where('cp.is_active', 1)
            ->when($childId > 0, fn ($query) => $query->where('cp.product_category_child_id', $childId))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(cp.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.bim_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.search_keywords) LIKE ?', [$like]);
                });
            })
            ->orderBy('cp.id')
            ->limit(30)
            ->get();

        return response()->json([
            'ok' => true,
            'results' => $rows->map(function ($row) {
                $name = $row->name_ar ?: ($row->name_en ?: ('#' . $row->id));
                $size = $row->package_label_ar ?: $row->package_label_en;
                return [
                    'id' => (int) $row->id,
                    'text' => trim($name . ($size ? ' - ' . $size : '') . ' (' . $row->bim_code . ')'),
                    'name' => $name,
                    'code' => $row->bim_code,
                    'child' => $row->child_name_ar,
                    'brand' => $row->brand_name_ar,
                    'size' => $size,
                ];
            })->values(),
        ]);
    }

    protected function businessLookup(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = DB::table('users')
            ->select('id', 'name', 'email', 'phone')
            ->where('type', 'business')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'ok' => true,
            'results' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'text' => ($row->name ?: ('#' . $row->id)) . ' - ID: ' . $row->id,
                'name' => $row->name ?: ('#' . $row->id),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('type', 'business'))],
            'catalog_product_id' => ['required', 'integer', 'exists:catalog_products,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'offer_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['nullable'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        DB::table('business_catalog_products')->updateOrInsert(
            ['business_id' => (int) $data['business_id'], 'catalog_product_id' => (int) $data['catalog_product_id']],
            [
                'price' => round((float) $data['price'], 2),
                'offer_price' => isset($data['offer_price']) && $data['offer_price'] !== null ? round((float) $data['offer_price'], 2) : null,
                'stock_quantity' => (float) ($data['stock_quantity'] ?? 0),
                'stock_status' => ((float) ($data['stock_quantity'] ?? 0)) > 0 ? 'in_stock' : 'out_of_stock',
                'is_available' => $request->boolean('is_available') ? 1 : 0,
                'status' => $data['status'] ?? 'active',
                'currency_code' => 'EGP',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'تم ربط المنتج بالمتجر بنجاح.');
    }

    public function destroy(int $id)
    {
        DB::table('business_catalog_products')->where('id', $id)->delete();
        return back()->with('success', 'تم حذف المنتج من المتجر.');
    }

    protected function baseRowsQuery(int $businessId, int $childId, string $q, string $status)
    {
        return DB::table('business_catalog_products as bcp')
            ->join('catalog_products as cp', 'cp.id', '=', 'bcp.catalog_product_id')
            ->leftJoin('users as u', 'u.id', '=', 'bcp.business_id')
            ->leftJoin('product_category_children as pcc', 'pcc.id', '=', 'cp.product_category_child_id')
            ->leftJoin('catalog_brands as cb', 'cb.id', '=', 'cp.brand_id')
            ->select('bcp.*', 'cp.bim_code', 'cp.name_ar as product_name_ar', 'cp.name_en as product_name_en', 'cp.package_label_ar', 'cp.package_label_en', 'cp.main_image', 'u.name as business_name', 'pcc.name_ar as child_name_ar', 'pcc.name_en as child_name_en', 'cb.name_ar as brand_name_ar', 'cb.name_en as brand_name_en')
            ->when($businessId > 0, fn ($query) => $query->where('bcp.business_id', $businessId))
            ->when($childId > 0, fn ($query) => $query->where('cp.product_category_child_id', $childId))
            ->when($status !== '', fn ($query) => $query->where('bcp.status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(cp.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.bim_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(u.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cb.name_ar) LIKE ?', [$like]);
                });
            });
    }
}
