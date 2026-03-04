<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{


    public function index(){

        return view('wishlists.index');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @@ Add product to wishlist.
     */
    public function addToWishList(Request $request)
    {

        $inputs = $request->except('_token');

        if (auth()->check()):
            if (auth()->user()->isInFavorite($inputs['productId'])) {
                auth()->user()->wishlists()->where('product_id', $inputs['productId'])->delete();
                return response()->json([
                    'status' => 200,
                    'checked' => false,
                    'message' => __('trans.product_removed_from_wishlist_successfully')
                ]);
            }
            $wishListSaved = auth()->user()->wishlists()->create(array_merge($inputs, array('product_id' => $inputs['productId'])));
            if ($wishListSaved):
                return response()->json([
                    'status' => 200,
                    'checked' => true,
                    'message' => __('trans.product_added_to_wishlist_successfully')
                ]);
            endif;

        else:
            return response()->json([
                'status' => 401,
                'message' => __('trans.should_be_logged_in')
            ]);
        endif;


    }


}
