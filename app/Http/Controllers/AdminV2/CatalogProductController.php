<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogProductController extends Controller
{
    public function index(Request $request)
    {
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

    public function inlineUpdate(Request $request, int $product): JsonResponse
    {
        $result = $this->updateSingleField($product, (string) $request->input('field', ''), $request->input('value'));
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function bulkAction(Request $request)
    {
        $redirectParams = $request->only(['q', 'child_id', 'brand_id', 'status', 'duplicate_status', 'per_page']);

        $action = (string) $request->input('manager_action', '');

        if ($action === '' || $action === 'inline_update') {
            return redirect()
                ->route('admin.catalog-products.index', $redirectParams)
                ->with('error', 'اختر إجراء صالح.');
        }

        $ids = collect((array) $request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(500)
            ->values();

        if ($ids->isEmpty()) {
            return redirect()
                ->route('admin.catalog-products.index', $redirectParams)
                ->with('error', 'اختر صنف واحد على الأقل.');
        }

        $this->applyBulkAction($action, $ids);

        return redirect()
            ->route('admin.catalog-products.index', $redirectParams)
            ->with('success', 'تم تنفيذ العملية بنجاح.');
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

    private function applyBulkAction(string $action, $ids): void
    {
        if ($action === 'delete_forever') {
            $this->deleteProductsForever($ids);
            return;
        }

        if (Schema::hasColumn('catalog_products', 'duplicate_status') && in_array($action, ['unique', 'review', 'duplicate'], true)) {
            DB::table('catalog_products')->whereIn('id', $ids)->update([
                'duplicate_status' => $action,
                'duplicate_master_id' => null,
            ]);
        }

        if ($action === 'inactive' && Schema::hasColumn('catalog_products', 'is_active')) {
            DB::table('catalog_products')->whereIn('id', $ids)->update(['is_active' => 0]);
        }

        if ($action === 'active' && Schema::hasColumn('catalog_products', 'is_active')) {
            DB::table('catalog_products')->whereIn('id', $ids)->update(['is_active' => 1]);
        }
    }

    private function updateSingleField(int $id, string $field, mixed $value): array
    {
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Invalid product.'];
        }

        $allowedText = ['name_ar', 'name_en', 'model', 'package_label_ar', 'package_label_en'];
        $allowedApprovalStatuses = ['draft', 'pending', 'approved', 'rejected'];
        $allowedDuplicateStatuses = ['unique', 'master', 'duplicate', 'review'];

        if (! Schema::hasColumn('catalog_products', $field)) {
            return ['ok' => false, 'message' => 'Field not found.'];
        }

        if (in_array($field, $allowedText, true)) {
            $clean = trim((string) $value);
            DB::table('catalog_products')->where('id', $id)->update([$field => $clean !== '' ? $clean : null]);
            return ['ok' => true, 'value' => $clean !== '' ? $clean : '—'];
        }

        if ($field === 'package_value') {
            $clean = trim((string) $value);
            DB::table('catalog_products')->where('id', $id)->update([$field => $clean !== '' ? (float) $clean : null]);
            return ['ok' => true, 'value' => $clean !== '' ? $clean : '—'];
        }

        if ($field === 'is_active') {
            $clean = (int) $value === 1 ? 1 : 0;
            DB::table('catalog_products')->where('id', $id)->update([$field => $clean]);
            return ['ok' => true, 'value' => $clean === 1 ? 'Active' : 'Inactive', 'raw' => (string) $clean];
        }

        if ($field === 'approval_status' && in_array((string) $value, $allowedApprovalStatuses, true)) {
            DB::table('catalog_products')->where('id', $id)->update([$field => (string) $value]);
            return ['ok' => true, 'value' => (string) $value];
        }

        if ($field === 'duplicate_status' && in_array((string) $value, $allowedDuplicateStatuses, true)) {
            $data = ['duplicate_status' => (string) $value];
            if ((string) $value !== 'duplicate') {
                $data['duplicate_master_id'] = null;
            }
            DB::table('catalog_products')->where('id', $id)->update($data);
            return ['ok' => true, 'value' => (string) $value];
        }

        return ['ok' => false, 'message' => 'Invalid value.'];
    }

    private function deleteProductsForever($ids): void
    {
        DB::transaction(function () use ($ids) {
            $relatedTables = [
                ['store_catalog_items', 'catalog_product_id'],
                ['catalog_product_attributes', 'product_id'],
                ['catalog_product_attribute_values', 'product_id'],
                ['catalog_product_images', 'product_id'],
                ['product_images', 'product_id'],
                ['product_barcodes', 'product_id'],
            ];

            foreach ($relatedTables as [$table, $column]) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->whereIn($column, $ids)->delete();
                }
            }

            DB::table('catalog_products')->whereIn('id', $ids)->delete();
        });
    }
}
