<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogBrandController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $verified = (string) $request->get('verified', '');

        $rows = DB::table('catalog_brands as cb')
            ->select('cb.*')
            ->when($verified !== '', fn ($query) => $query->where('is_verified', (int) $verified))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
                });
            })
            ->orderBy('name_ar')
            ->paginate(80)
            ->withQueryString();

        $productCounts = DB::table('catalog_products')
            ->select('brand_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('brand_id')
            ->groupBy('brand_id')
            ->pluck('c', 'brand_id');

        $stats = [
            'total' => DB::table('catalog_brands')->count(),
            'active' => DB::table('catalog_brands')->where('is_active', 1)->count(),
            'verified' => DB::table('catalog_brands')->where('is_verified', 1)->count(),
            'products' => DB::table('catalog_products')->whereNotNull('brand_id')->count(),
        ];

        return view('admin-v2.catalog-brands.index', compact('rows', 'productCounts', 'stats', 'q', 'verified'));
    }
}
