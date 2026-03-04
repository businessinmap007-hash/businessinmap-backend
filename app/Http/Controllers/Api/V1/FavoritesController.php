<?php

namespace App\Http\Controllers\Api\V1;

use App\Company;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FavoritesController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function favoriteCompany(Request $request)
    {
        $user = auth()->user();
        try {
            if ($request->type == 1):
                $user->favorites()->syncWithoutDetaching($request->companyId);
                $message = "لقد تم إضافة المنشأة للمفضلة";
            else:
                $user->favorites()->detach($request->companyId);
                $message = "لقد تم إزالة المنشأة للمفضلة";

            endif;

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => []
            ]);

        } catch (QueryException $e) {

            return response()->json([
                'status' => false,
                'message' => 'erroraddtofavorite',
                'data' => []
            ]);
        }
    }


    public function getFavoriteListForUser(Request $request)
    {
        $user = User::whereApiToken($request->api_token)->first();
        $arrs = [];
        foreach ($user->favorites as $row) {
            $arrs[] = $row->id;
        }
        /**
         * Set Default Value For Skip Count To Avoid Error In Service.
         * @ Default Value 15...
         */
        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 15;
        endif;
        /**
         * SkipCount is Number will Skip From Array
         */
        $skipCount = $request->skipCount;
        $itemId = $request->itemId;

        $currentPage = $request->get('page', 1); // Default to 1
        $query = Company::whereIn('id', $arrs);

        /**
         * @ If item Id Exists skipping by it.
         */
        if ($itemId) {
            $query->where('id', '<=', $itemId);
        }

        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */
        $favorites = $query->get();


        $favorites->map(function ($q) use ($request) {

            $q->likes = $q->likes()->where('like', 1)->count();
            $q->dislike = $q->likes()->where('like', 0)->count();
            $q->favorites = $q->favorites()->count();
            $q->ratings = $q->averageRating();
            $q->visits = $q->visits()->count();
            $q->phone = ($user = $this->companyCompleteFromUser($q->id)) ? $user->phone : null;
            $q->city = $this->getCityForCompany($q->id);
            $q->commentsCount = $this->getCountsForCompany($q->id);
            $q->membership = $this->getMembershipForCompany($q->id);
            $q->averageRating = ($q->ratings()->count() > 5) ? $q->averageRating : 0;
            if ($request->api_token) {
                $currentUser = User::whereApiToken($request->api_token)->first();
                $userRate = $q->ratings()->where('user_id', $currentUser->id)->first();
                $q->userRatings = (isset($userRate)) ? $userRate->rating : 0;
            }
            $q->userRatings = 0;
        });


        /**
         * Return Data Array
         */
        return response()->json([
            'status' => true,
            'data' => $favorites
        ]);


    }


    private function getCountsForCompany($company)
    {
        $company = Company::with('comments')->whereId($company)->first();

        return ($company && $company->comments) ? $company->comments->count() : NULL;
    }


    /**
     * @param $company
     * @return array|null
     */

    private function getMembershipForCompany($company)
    {
        $company = Company::with('membership')->whereId($company)->first();
        return ($company && $company->membership) ? [
            'id' => $company->membership->id,
            'name' => $company->membership->name,
            'color' => $company->membership->color
        ] : NULL;
    }


    private function companyCompleteFromUser($company)
    {
        $company = Company::with('user')->whereId($company)->first();
        return ($company && $company->user) ? $company->user : NULL;
    }

    /**
     * @param $company
     * @return null
     */
    private function getCityForCompany($company)
    {
        $company = Company::with('city')->whereId($company)->first();
        return ($company && $company->city) ? $company->city->name : NULL;
    }





//    public function favorite(Request $request)
//    {
//        $user = User::whereApiToken($request->api_token)->first();
//        try {
//            $user->favorites()->syncWithoutDetaching($request->advId);
//            return response()->json([
//                'status' => true,
//                'message' => 'successaddtofavorite',
//                'data' => []
//            ]);
//
//        } catch (QueryException $e) {
//
//            return response()->json([
//                'status' => false,
//                'message' => 'erroraddtofavorite',
//                'data' => []
//            ]);
//        }
//    }
//
//
//    public function unfavorite(Request $request)
//    {
//        $user = User::whereApiToken($request->api_token)->first();
//        try {
//
//            $user->favorites()->detach($request->advId);
//            return response()->json([
//                'status' => true,
//                'message' => 'successremovefromfavorite',
//                'data' => []
//            ]);
//        } catch (QueryException $e) {
//
//            return response()->json([
//                'status' => false,
//                'message' => 'errorremovefromfavorite',
//                'data' => []
//            ]);
//        }
//    }
//
//
//    public function favoritesList(Request $request, $pageSize = 15)
//    {
//        $user = User::whereApiToken($request->api_token)
//            ->with('favorites')
//            ->first();
//
//
//        $favs = $user->favorites;
//        $favs->map(function ($q)  {
//            $q->names = $this->getLangsNames($q->id);
//
//
//            $q->cities = $this->getCityNames($q->id);
//
//            return $q;
//        });
//
//
//
//        if ($favs->count() > 0)
//            return response()->json([
//                'status' => true,
//                'data' => $this->paginate($favs, $request->pageSize, $request->page)
//            ]);
//        else
//            return response()->json([
//                'status' => true,
//                'data' => $this->paginate($favs, $request->pageSize, $request->page)
//            ]);
//    }


}
