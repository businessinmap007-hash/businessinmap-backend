<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogProductController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $childId = (int) $request->get('child_id', 0);
        $brandId = (int) $request->get('brand_id', 0);
        $status = (string) $request->get('status', '');

        $children = DB::table('product_category_children')->select('id', 'name_ar', 'name_en')->orderBy('sort_order')->orderBy('id')->get();
        $brands = DB::table('catalog_brands')->select('id', 'name_ar', 'name_en')->orderBy('name_ar')->limit(500)->get();

        $rows = $this->baseQuery($q, $childId, $brandId, $status)
            ->orderByDesc('cp.id')
            ->paginate(80)
            ->withQueryString();

        $stats = [
            'total' => DB::table('catalog_products')->count(),
            'active' => DB::table('catalog_products')->where('is_active', 1)->count(),
            'approved' => DB::table('catalog_products')->where('approval_status', 'approved')->count(),
            'children' => DB::table('product_category_children')->count(),
        ];

        return view('admin-v2.catalog-products.index', compact('rows', 'children', 'brands', 'stats', 'q', 'childId', 'brandId', 'status'));
    }

    protected function baseQuery(string $q, int $childId, int $brandId, string $status)
    {
        return DB::table('catalog_products as cp')
            ->leftJoin('product_category_children as pcc', 'pcc.id', '=', 'cp.product_category_child_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'cp.product_category_id')
            ->leftJoin('catalog_brands as cb', 'cb.id', '=', 'cp.brand_id')
            ->leftJoin('catalog_units as cu', 'cu.id', '=', 'cp.unit_id')
            ->select(
                'cp.id', 'cp.bim_code', 'cp.name_ar', 'cp.name_en', 'cp.model', 'cp.main_image',
                'cp.package_value', 'cp.package_label_ar', 'cp.package_label_en', 'cp.is_active', 'cp.approval_status',
                'pc.name_ar as category_name_ar', 'pcc.name_ar as child_name_ar', 'cb.name_ar as brand_name_ar', 'cu.code as unit_code'
            )
            ->when($childId > 0, fn ($query) => $query->where('cp.product_category_child_id', $childId))
            ->when($brandId > 0, fn ($query) => $query->where('cp.brand_id', $brandId))
            ->when($status !== '', function ($query) use ($status) {
                if ($status === 'active') $query->where('cp.is_active', 1);
                if ($status === 'inactive') $query->where('cp.is_active', 0);
                if (in_array($status, ['draft','pending','approved','rejected'], true)) $query->where('cp.approval_status', $status);
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(cp.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.bim_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.model) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cb.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cp.search_keywords) LIKE ?', [$like]);
                });
            });
    }
}
