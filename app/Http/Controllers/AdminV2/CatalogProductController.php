<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogProductController extends Controller
{
    /** Blank rows always offered under an existing product's own specs, to add more. */
    private const BLANK_SPEC_ROWS = 6;

    /**
     * «ابني شاشة CRUD لإضافة موديلات ومواصفات جديدة» — a real laptop/phone/
     * appliance model plus its spec table, in one screen (see
     * [[menu-catalog-specs-link]] — this closes the admin-CRUD gap that
     * memory flagged: browsing/inline-editing already existed, creating a
     * brand-new model never did).
     */
    public function create(): View
    {
        return view('admin-v2.catalog-products.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $id = DB::transaction(function () use ($data, $request) {
            $id = DB::table('catalog_products')->insertGetId($data + [
                'bim_code' => $this->generateBimCode(),
                'product_type' => 'simple',
                'market_scope' => 'egypt',
                'duplicate_status' => 'unique',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncSpecs($id, $request->input('specs', []));

            return $id;
        });

        return redirect()->route('admin.catalog-products.edit', $id)->with('success', __('تم إنشاء المنتج بنجاح.'));
    }

    public function edit(int $product): View
    {
        $row = DB::table('catalog_products')->where('id', $product)->first();
        abort_if(! $row, 404);

        $specs = DB::table('catalog_product_attribute_values as v')
            ->join('catalog_attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('v.product_id', $product)
            ->orderBy('a.sort_order')->orderBy('a.id')
            ->get(['v.id', 'v.attribute_id', 'a.name_ar as attribute_name', 'v.value_text_ar', 'v.value_number']);

        return view('admin-v2.catalog-products.edit', $this->formData() + [
            'row' => $row,
            'specs' => $specs,
            'blankSpecRows' => self::BLANK_SPEC_ROWS,
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        abort_if(! DB::table('catalog_products')->where('id', $product)->exists(), 404);

        $data = $this->validated($request, $product);

        DB::transaction(function () use ($data, $product, $request) {
            DB::table('catalog_products')->where('id', $product)->update($data + ['updated_at' => now()]);

            $removeIds = collect((array) $request->input('remove_spec_ids', []))->map(fn ($id) => (int) $id)->filter();
            if ($removeIds->isNotEmpty()) {
                DB::table('catalog_product_attribute_values')->where('product_id', $product)->whereIn('id', $removeIds)->delete();
            }

            $this->syncSpecs($product, $request->input('specs', []));
        });

        return redirect()->route('admin.catalog-products.edit', $product)->with('success', __('تم تحديث المنتج بنجاح.'));
    }

    /** Shared select-list data for both the create and edit forms. */
    private function formData(): array
    {
        return [
            'categories' => DB::table('product_categories')->select('id', 'name_ar', 'name_en')->orderBy('name_ar')->get(),
            'children' => DB::table('product_category_children')->select('id', 'name_ar', 'name_en', 'product_category_id')->orderBy('sort_order')->orderBy('id')->get(),
            'brandOptions' => DB::table('catalog_brands')->select('id', 'name_ar', 'name_en')->orderBy('name_ar')->limit(500)->get(),
            'unitOptions' => DB::table('catalog_units')->select('id', 'name_ar', 'name_en', 'code')->orderBy('name_ar')->get(),
            'attributeOptions' => DB::table('catalog_attributes')->select('id', 'name_ar', 'name_en', 'data_type')->orderBy('sort_order')->orderBy('name_ar')->get(),
            'blankSpecRows' => self::BLANK_SPEC_ROWS,
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'product_category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')],
            'product_category_child_id' => ['required', 'integer', Rule::exists('product_category_children', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('catalog_brands', 'id')],
            'unit_id' => ['nullable', 'integer', Rule::exists('catalog_units', 'id')],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'short_name_ar' => ['nullable', 'string', 'max:180'],
            'model' => ['nullable', 'string', 'max:160'],
            'main_image' => ['nullable', 'string', 'max:255'],
            'package_value' => ['nullable', 'numeric'],
            'package_label_ar' => ['nullable', 'string', 'max:80'],
            'package_label_en' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'in:0,1'],
        ];

        $data = $request->validate($rules);

        $data['is_active'] = (int) ($data['is_active'] ?? 1);
        foreach (['brand_id', 'unit_id', 'package_value'] as $nullableKey) {
            if (($data[$nullableKey] ?? '') === '') {
                $data[$nullableKey] = null;
            }
        }

        return $data;
    }

    /**
     * Writes every submitted spec row that names an attribute and carries a
     * value — text OR number, never both — as an upsert (product_id +
     * attribute_id is the natural key, matching how the seeders/import write
     * the same table). Blank rows (no attribute picked) are silently
     * ignored, same as the bulk pickers' own "empty means nothing to do".
     */
    private function syncSpecs(int $productId, array $rows): void
    {
        foreach ($rows as $row) {
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            if ($attributeId <= 0) {
                continue;
            }

            $textAr = trim((string) ($row['value_text'] ?? ''));
            $number = trim((string) ($row['value_number'] ?? ''));

            if ($textAr === '' && $number === '') {
                continue;
            }

            DB::table('catalog_product_attribute_values')->updateOrInsert(
                ['product_id' => $productId, 'attribute_id' => $attributeId],
                [
                    'value_text_ar' => $number === '' ? $textAr : null,
                    'value_number' => $number !== '' ? (float) $number : null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /** «BIM-NEW-YYYYMMDD-####» — never collides with an imported bim_code. */
    private function generateBimCode(): string
    {
        $prefix = 'BIM-NEW-' . now()->format('Ymd') . '-';

        do {
            $code = $prefix . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('catalog_products')->where('bim_code', $code)->exists());

        return $code;
    }

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
            'total' => $this->catalogQuery()->count(),
            'active' => $this->catalogQuery()->where('is_active', 1)->count(),
            'approved' => $this->catalogQuery()->where('approval_status', 'approved')->count(),
            'children' => DB::table('product_category_children')->count(),
            'unique' => Schema::hasColumn('catalog_products', 'duplicate_status') ? $this->catalogQuery()->where('duplicate_status', 'unique')->count() : null,
            'master' => Schema::hasColumn('catalog_products', 'duplicate_status') ? $this->catalogQuery()->where('duplicate_status', 'master')->count() : null,
            'duplicate' => Schema::hasColumn('catalog_products', 'duplicate_status') ? $this->catalogQuery()->where('duplicate_status', 'duplicate')->count() : null,
            'review' => Schema::hasColumn('catalog_products', 'duplicate_status') ? $this->catalogQuery()->where('duplicate_status', 'review')->count() : null,
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
                ->with('error', __('اختر إجراء صالح.'));
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
                ->with('error', __('اختر صنف واحد على الأقل.'));
        }

        $this->applyBulkAction($action, $ids);

        return redirect()
            ->route('admin.catalog-products.index', $redirectParams)
            ->with('success', __('تم تنفيذ العملية بنجاح.'));
    }

    /** Base builder that excludes soft-deleted rows (e.g. merged duplicates). */
    protected function catalogQuery(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('catalog_products');

        if (Schema::hasColumn('catalog_products', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
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
            ->when(Schema::hasColumn('catalog_products', 'deleted_at'), fn ($query) => $query->whereNull('cp.deleted_at'))
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
