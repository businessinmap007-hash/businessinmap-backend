<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class CategoryController extends Controller
{


    public function __construct(Request $request)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);
    }

    /**
     * @@ Get All Categories
     */
    public function index(Request $request)
    {

        $query = Category::orderBy('reorder', 'asc');


        if ($request->has('parentId') && $request->get('parentId') != "") {
            $query->whereParentId($request->get('parentId'));
        } else {
            $query->whereParentId(0);
        }

        $categories = $query->get();


        $followCategories = [];
        $targetCategories = [];
        if ($token = str_replace('Bearer ', '', request()->headers->get('Authorization'))) {
            $user = User::whereApiToken($token)->first();
            $followCategories = $user->categoryFollows->pluck('id');
            $targetCategories = $user->categoryTargets->pluck('id');
        }


        return CategoryResource::collection($categories)
            ->additional(['status' => 200,
                'follows' => $followCategories,
                'targets' => $targetCategories]);


    }


    /**
     * @return \Illuminate\Http\JsonResponse
     */

    public function indexWithPaginationTakeSkip(Request $request)
    {
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
        $query = Category::whereParentId(0);

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
        $categories = $query->get();

        /**
         * Return Data Array
         */
        return response()->json([
            'status' => true,
            'data' => $categories
        ]);

    }


    public function getSubCategories(Request $request, $id)
    {

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
        $query = Category::whereParentId($id);

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
        $categories = $query->get();

        /**
         * Return Data Array
         */
        return response()->json([
            'status' => true,
            'data' => $categories
        ]);

    }

}
