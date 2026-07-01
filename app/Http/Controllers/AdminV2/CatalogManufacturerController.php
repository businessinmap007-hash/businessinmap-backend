<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogManufacturerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('catalog_manufacturers'), 404);

        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $productForeignKey = $this->resolveProductForeignKey();

        $query = DB::table('catalog_manufacturers as m')->select('m.*');

        if ($productForeignKey !== null) {
            $query->selectRaw("(
                SELECT COUNT(*)
                FROM catalog_products p
                WHERE p.{$productForeignKey} = m.id
            ) as products_count");
        } else {
            $query->selectRaw('0 as products_count');
        }

        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                foreach (['name_ar', 'name_en', 'name', 'code', 'slug'] as $column) {
                    if (Schema::hasColumn('catalog_manufacturers', $column)) {
                        $where->orWhere('m.' . $column, 'like', '%' . $q . '%');
                    }
                }
            });
        }

        if ($status !== '') {
            if (Schema::hasColumn('catalog_manufacturers', 'is_active')) {
                $query->where('m.is_active', (int) $status);
            } elseif (Schema::hasColumn('catalog_manufacturers', 'status')) {
                $query->where('m.status', $status);
            }
        }

        $items = $query->orderByDesc('m.id')->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DB::table('catalog_manufacturers')->count(),
            'active' => Schema::hasColumn('catalog_manufacturers', 'is_active')
                ? DB::table('catalog_manufacturers')->where('is_active', 1)->count()
                : null,
            'inactive' => Schema::hasColumn('catalog_manufacturers', 'is_active')
                ? DB::table('catalog_manufacturers')->where('is_active', 0)->count()
                : null,
            'products' => $productForeignKey !== null
                ? DB::table('catalog_products')->whereNotNull($productForeignKey)->count()
                : 0,
        ];

        return view('admin-v2.catalog-manufacturers.index', compact(
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

        foreach (['manufacturer_id', 'catalog_manufacturer_id'] as $column) {
            if (Schema::hasColumn('catalog_products', $column)) {
                return $column;
            }
        }

        return null;
    }
}
