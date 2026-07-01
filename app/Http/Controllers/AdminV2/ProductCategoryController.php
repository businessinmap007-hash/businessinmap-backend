<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = DB::table('product_categories as pc')
            ->select('pc.*')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $childrenCounts = DB::table('product_category_children')
            ->select('product_category_id', DB::raw('COUNT(*) as c'))
            ->groupBy('product_category_id')
            ->pluck('c', 'product_category_id');

        $productCounts = DB::table('catalog_products')
            ->select('product_category_id', DB::raw('COUNT(*) as c'))
            ->groupBy('product_category_id')
            ->pluck('c', 'product_category_id');

        $stats = [
            'total' => DB::table('product_categories')->count(),
            'active' => DB::table('product_categories')->where('is_active', 1)->count(),
            'children' => DB::table('product_category_children')->count(),
            'products' => DB::table('catalog_products')->count(),
        ];

        return view('admin-v2.product-categories.index', compact('rows', 'childrenCounts', 'productCounts', 'stats', 'q'));
    }
}
