<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogProductController extends Controller
{
    public function index(Request $request)
    {
        $this->handleBulkAction($request);

        $q = trim((string) $request->get('q', ''));
        $childId = (int) $request->get('child_id', 0);
        $brandId = (int) $request->get('brand_id', 0);
        $status = (string) $request->get('status', '');
        $duplicateStatus = (string) $request->get('duplicate_status', '');
        $perPage = (int) $request->get('per_page', 100);
        $perPage = in_array($perPage, [50, 100, 200, 500], true) ? $perPage : 100;

        $children = DB::table('product_category_children')->select('id', 'name_ar', 'name_en')->orderBy('sort_order')->orderBy('id')->get();
        $brands = DB::table('catalog_brands')->select('id', 'name_ar', 'name_en')->orderBy('name_ar')->limit(500)->get();

        $rows = $this->baseQuery($q, $childId, $brandId, $status, $duplicateStatus)
            ->orderByRaw("COALESCE(NULLIF(cp.name_ar, ''), cp.name_en) ASC")
            ->orderBy('cp.package_value')
            ->orderBy('cp.brand_id')
            ->orderBy('cp.id')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total' => DB::table('catalog_products')->count(),
            'active' => DB::table('catalog_products')->where('is_active', 1)->count(),
            'approved' => DB::table('catalog_products')->where('approval_status', 'approved')->count(),
            'children' => DB::table('product_category_children')->count(),
            'unique' => Schema::hasColumn('catalog_products', 'duplicate_status') ? DB::table('catalog_products')->where('duplicate_status', 'unique')->count() : null,
            'master' => Schema::hasColumn('catalog_products', 'duplicate_status') ? DB::table('catalog_products')->where('duplicate_status', 'master')->count() : null,
            'duplicate' => Schema::hasColumn('catalog_products', 'duplicate_status') ? DB::table('catalog_products')->where('duplicate_status', 'duplicate')->count() : null,
            'review' => Schema::hasColumn('catalog_products', 'duplicate_status') ? DB::table('catalog_products')->where('duplicate_status', 'review')->count() : null,
        ];

        return view('admin-v2.catalog-products.index', compact('rows', 'children', 'brands', 'stats', 'q', 'childId', 'brandId', 'status', 'duplicateStatus', 'perPage'));
    }

    protected function baseQuery(string $q, int $childId, int $brandId, string $status, string $duplicateStatus)
    {
        return DB::table('catalog_products as cp')
            ->leftJoin('product_category_children as pcc', 'pcc.id', '=', 'cp.product_category_child_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'cp.product_category_id')
            ->leftJoin('catalog_brands as cb', 'cb.id', '=', 'cp.brand_id')
            ->leftJoin('catalog_units as cu', 'cu.id', '=', 'cp.unit_id')
            ->select(
                'cp.id', 'cp.bim_code', 'cp.name_ar', 'cp.name_en', 'cp.model', 'cp.main_image',
                'cp.package_value', 'cp.package_label_ar', 'cp.package_label_en', 'cp.is_active', 'cp.approval_status',
                'cp.duplicate_status', 'cp.duplicate_master_id',
                'pc.name_ar as category_name_ar', 'pcc.name_ar as child_name_ar', 'cb.name_ar as brand_name_ar', 'cu.code as unit_code'
            )
            ->when($childId > 0, fn ($query) => $query->where('cp.product_category_child_id', $childId))
            ->when($brandId > 0, fn ($query) => $query->where('cp.brand_id', $brandId))
            ->when($duplicateStatus !== '', fn ($query) => $query->where('cp.duplicate_status', $duplicateStatus))
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
                        ->orWhereRaw('LOWER(cb.name_ar) LIKE ?', [$like]);
                });
            });
    }

    private function handleBulkAction(Request $request): void
    {
        if (! Schema::hasColumn('catalog_products', 'duplicate_status')) {
            return;
        }

        $action = (string) $request->get('manager_action', '');
        if ($action === '' || (string) $request->get('confirm_action', '') !== 'yes') {
            return;
        }

        $ids = collect((array) $request->get('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(500)
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        if (in_array($action, ['unique', 'review', 'duplicate'], true)) {
            DB::table('catalog_products')->whereIn('id', $ids)->update([
                'duplicate_status' => $action,
                'duplicate_master_id' => null,
            ]);
        }

        if ($action === 'inactive') {
            DB::table('catalog_products')->whereIn('id', $ids)->update(['is_active' => 0]);
        }

        if ($action === 'active') {
            DB::table('catalog_products')->whereIn('id', $ids)->update(['is_active' => 1]);
        }
    }
}
