<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @@ Get Products Pagging. and some filters.
     */
    public function index(Request $request)
    {


        $query = Product::select();




        if ($request->has('s') && $request->get('s') != "")
            $query->whereHas('translations', function ($obj) use ($request) {
                $keyWord = $request->get('s');
                $obj->where('name', 'LIKE', "%$keyWord%");
            });


        if ($request->has('category') && $request->get('category') != "") {
            $ids = $this->getSubCategoriesIds($request->category);
            $query->whereIn('category_id', $ids);
        }


        if ($request->has('price-from') && $request->get('price-from') != "")
            $query->where('price', '>=', $request->get('price-from'));

        if ($request->has('price-to') && $request->get('price-to') != "")
            $query->where('price', '<=', $request->get('price-to'));


        if ($request->has('country') && $request->get('country') != "")
            $query->where('location_id', $request->get('country'));




        if ($request->has('price') && $request->get('price') != "")
            $query->orderBy('price', $request->get('price'));


        if ($request->has('order') && $request->get('order') != "")
            $query->orderBy('created_at', $request->get('order'));





        $products = $query->paginate(12);

        $countries = Location::whereParentId(0)->get();

        return view('products.index', compact('products', 'countries'));

    }

    private function getSubCategoriesIds($parentIds)
    {
        $categories = Category::whereIn('id', [$parentIds])->get();
        $childrens = [];
        foreach ($categories as $category) {
            $childrens[] = $category->children->pluck('id');
        }
        return collect($childrens)->collapse();
    }


    public function details(Product $product)
    {
        if (!$product)
            return abort(404);

        $product->load('ratings');

        $relatedProducts = Product::inRandomOrder()->whereCategoryId($product->category_id)->limit(4)->get();
        return view('products.details', compact('product', 'relatedProducts'));
    }


}
