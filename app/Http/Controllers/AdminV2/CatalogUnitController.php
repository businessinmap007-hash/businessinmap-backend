<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogUnitController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('catalog_units'), 404);

        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $productForeignKey = $this->resolveProductForeignKey();

        $query = DB::table('catalog_units as u')->select('u.*');

        if ($productForeignKey !== null) {
            $query->selectRaw("(
                SELECT COUNT(*)
                FROM catalog_products p
                WHERE p.{$productForeignKey} = u.id
            ) as products_count");
        } else {
            $query->selectRaw('0 as products_count');
        }

        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                foreach (['name_ar', 'name_en', 'name', 'symbol', 'code', 'slug'] as $column) {
                    if (Schema::hasColumn('catalog_units', $column)) {
                        $where->orWhere('u.' . $column, 'like', '%' . $q . '%');
                    }
                }
            });
        }

        if ($status !== '') {
            if (Schema::hasColumn('catalog_units', 'is_active')) {
                $query->where('u.is_active', (int) $status);
            } elseif (Schema::hasColumn('catalog_units', 'status')) {
                $query->where('u.status', $status);
            }
        }

        $items = $query->orderByDesc('u.id')->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DB::table('catalog_units')->count(),
            'active' => Schema::hasColumn('catalog_units', 'is_active')
                ? DB::table('catalog_units')->where('is_active', 1)->count()
                : null,
            'inactive' => Schema::hasColumn('catalog_units', 'is_active')
                ? DB::table('catalog_units')->where('is_active', 0)->count()
                : null,
            'products' => $productForeignKey !== null
                ? DB::table('catalog_products')->whereNotNull($productForeignKey)->count()
                : 0,
        ];

        return view('admin-v2.catalog-units.index', compact(
            'items',
            'stats',
            'q',
            'status',
            'perPage'
        ));
    }

    private function resolveProductForeignKey(): ?string
    {
        if (! Schema::hasTable('catalog_products')) {
            return null;
        }

        foreach (['unit_id', 'catalog_unit_id'] as $column) {
            if (Schema::hasColumn('catalog_products', $column)) {
                return $column;
            }
        }

        return null;
    }
}
