<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogAttributeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('catalog_attributes'), 404);

        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $valuesTable = $this->resolveValuesTable();
        $productPivot = $this->resolveProductAttributePivot();

        $query = DB::table('catalog_attributes as a')->select('a.*');

        if ($valuesTable !== null) {
            $query->selectRaw("(
                SELECT COUNT(*)
                FROM {$valuesTable} av
                WHERE av.attribute_id = a.id
            ) as values_count");
        } else {
            $query->selectRaw('0 as values_count');
        }

        if ($productPivot !== null) {
            $query->selectRaw("(
                SELECT COUNT(DISTINCT pa.product_id)
                FROM {$productPivot} pa
                WHERE pa.attribute_id = a.id
            ) as products_count");
        } else {
            $query->selectRaw('0 as products_count');
        }

        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                foreach (['name_ar', 'name_en', 'name', 'code', 'type', 'input_type', 'slug'] as $column) {
                    if (Schema::hasColumn('catalog_attributes', $column)) {
                        $where->orWhere('a.' . $column, 'like', '%' . $q . '%');
                    }
                }
            });
        }

        if ($status !== '') {
            if (Schema::hasColumn('catalog_attributes', 'is_active')) {
                $query->where('a.is_active', (int) $status);
            } elseif (Schema::hasColumn('catalog_attributes', 'status')) {
                $query->where('a.status', $status);
            }
        }

        $items = $query->orderByDesc('a.id')->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DB::table('catalog_attributes')->count(),
            'active' => Schema::hasColumn('catalog_attributes', 'is_active')
                ? DB::table('catalog_attributes')->where('is_active', 1)->count()
                : null,
            'inactive' => Schema::hasColumn('catalog_attributes', 'is_active')
                ? DB::table('catalog_attributes')->where('is_active', 0)->count()
                : null,
            'values' => $valuesTable !== null ? DB::table($valuesTable)->count() : 0,
        ];

        return view('admin-v2.catalog-attributes.index', compact(
            'items',
            'stats',
            'q',
            'status',
            'perPage'
        ));
    }

    private function resolveValuesTable(): ?string
    {
        foreach (['catalog_attribute_values', 'catalog_attribute_value'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'attribute_id')) {
                return $table;
            }
        }

        return null;
    }

    private function resolveProductAttributePivot(): ?string
    {
        foreach (['catalog_product_attributes', 'catalog_product_attribute', 'catalog_product_attribute_values'] as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'attribute_id')
                && Schema::hasColumn($table, 'product_id')
            ) {
                return $table;
            }
        }

        return null;
    }
}
