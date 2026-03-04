<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BusinessController extends Controller
{
    /**
     * @param Request $request
     * @param Category $category
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */


    public function index(Request $request, $categoryId)
    {


        $query = User::whereType('business');

        if ($categoryId != "")
            $query->whereIn('category_id', Category::whereParentId($categoryId)->pluck('id'));

        if ($request->categoriesIds != "")
            $query->whereIn('category_id', $request->categoriesIds);

        if (isset($request->name) && $request->name != "")
            $query->where('name', 'LIKE', "%$request->name%");

        if (isset($request->code) && $request->code != "")
            $query->where('code', 'LIKE', "%$request->code%");


        $business = $query->paginate(15);

        return UserResource::collection($business)->additional(['message' => "Message", 'status' => 200]);
    }


    public function getBusinessList(Request $request)
    {


        $query = User::orderBy('created_at', 'desc');


        if (isset($request->name) && $request->name != "")
            $query->where('name', 'LIKE', "%$request->name%");

        if (isset($request->code) && $request->code != "")
            $query->where('code', 'LIKE', "%$request->code%");

        if (isset($request->locationId) && $request->locationId != "") {
            $query->where('location_id', (int)$request->locationId);
        }

        if (isset($request->categoryIds) && $request->categoryIds != "") {

            $categoriesIds = explode(',', $request->categoryIds);

            $query->whereIn('category_id', $categoriesIds);

            if (isset($request->businessOptions) && $request->businessOptions != "") {
                $businessOptions = explode(',', $request->businessOptions);
                if (count($businessOptions)) {
                    $query->whereHas('options', function ($option) use ($businessOptions) {
                        $option->whereIn('option_id', $businessOptions);
                    });
                }
            }

        }


        if ($token = str_replace('Bearer ', '', request()->headers->get('Authorization'))) {
            $user = User::whereApiToken($token)->first();
            $query->where('id', '!=', $user->id);
        }


        if (!isset($request->value) || $request->value == "") {
            $query->whereType('business')->whereHas('subscriptions', function ($subscription) {
                $subscription->where('is_active', 1);
            });;
        }




        $businesses = $query->paginate(15);

        $businesses->map(function ($business) {
            $business->rate = $business->averageRating == null ? 0 : (int)$business->averageRating;
        });

        if (isset($request->orderByRate))
            $businesses = $businesses->sortBy('rate', SORT_REGULAR, $request->orderByRate == "desc" ? true : false)->values()->all();


        return UserResource::collection($businesses)->additional(['message' => "Message", 'status' => 200]);
    }


}
