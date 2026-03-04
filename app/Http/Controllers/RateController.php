<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use willvincent\Rateable\Rating;

class RateController extends Controller
{

    public function postRate(Request $request)
    {

        $product = Product::findOrFail($request->productId);

        if (!$product) {
            return response()->json([
                'status' => 400,
                'message' => __('trans.product_not_found'),
            ]);
        }


        $rating = new Rating();
        $userRatingBefore = $rating->where('rateable_id', $request->productId)->where('user_id', auth()->id())->first();
        if ($userRatingBefore) {
            return response()->json([
                'status' => 400,
                'message' => __('trans.order_rated_before'),
            ]);
        }
        $rating->rating = $request->rate;
        $rating->comment = $request->comment;
        $rating->user_id = auth()->id();
        if ($product->ratings()->save($rating))
            return response()->json([
                'status' => 200,
                'message' => __('trans.review_posted_successfully'),
                'comment' => view('products.reviews.review', compact('rating'))->render(),
                'avgRating' => $product->averageRating
            ]);
    }

}
