<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCategoryChildController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $categoryId = (int) $request->get('category_id', 0);

        $categories = DB::table('product_categories')
            ->select('id', 'name_ar', 'name_en')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = DB::table('product_category_children as pcc')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'pcc.product_category_id')
            ->select('pcc.*', 'pc.name_ar as category_name_ar', 'pc.name_en as category_name_en')
            ->when($categoryId > 0, fn ($query) => $query->where('pcc.product_category_id', $categoryId))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(pcc.name_ar) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(pcc.name_en) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(pcc.slug) LIKE ?', [$like]);
                });
            })
            ->orderBy('pcc.product_category_id')
            ->orderBy('pcc.sort_order')
            ->orderBy('pcc.id')
            ->paginate(80)
            ->withQueryString();

        $productCounts = DB::table('catalog_products')
            ->select('product_category_child_id', DB::raw('COUNT(*) as c'))
            ->groupBy('product_category_child_id')
            ->pluck('c', 'product_category_child_id');

        $stats = [
            'total' => DB::table('product_category_children')->count(),
            'active' => DB::table('product_category_children')->where('is_active', 1)->count(),
            'products' => DB::table('catalog_products')->count(),
            'categories' => DB::table('product_categories')->count(),
        ];

        return view('admin-v2.product-category-children.index', compact('rows', 'categories', 'productCounts', 'stats', 'q', 'categoryId'));
    }
}
