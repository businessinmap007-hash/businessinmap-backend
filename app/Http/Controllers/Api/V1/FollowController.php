<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FollowController extends Controller
{


    public function __construct()
    {
        $language = request()->headers->get('lang') ? request()->headers->get('lang') : 'ar';
        app()->setLocale($language);

    }

    public function index(Request $request)
    {


        $followers = $request->user()->followers;

        if ($request->has('keyword') && $request->get('keyword') != "") {
            $keyword = $request->get('keyword');
            $followers = $request->user()->followers()->where('name', 'LIKE', "%$keyword%")
                ->orWhere('follow_id', 'LIKE', "%$keyword%")->get();
        }


        $listCategories = [];

        $mainCategories = Category::whereIn('id', $request->user()->categoryFollows->pluck('parent_id')->unique())->get();

        foreach ($mainCategories as $mainCategory):
            $listCategories[] = array(

                'id' => $mainCategory->id,
                'name' => $mainCategory->name,
                'image' => $mainCategory->image,
                'children' => $this->getChildArr($mainCategory, $request->user()->categoryFollows->pluck('id')->unique())

            );
        endforeach;


        return response()->json([
            'status' => 200,
            'data' => [
                'users' => UserResource::collection($followers),
                'categories' => $listCategories
            ]
        ]);
    }


    private function getChildArr(Category $mainCategory, $subCatsIds)
    {

        $subCategories = Category::whereIn('id', $subCatsIds)->get();


        $children = [];
        foreach ($subCategories as $subCategory):
            if ($subCategory->parent_id == $mainCategory->id):
                $children[] = [
                    'id' => $subCategory->id,
                    'name' => $subCategory->name
                ];
            endif;
        endforeach;

        return $children;

    }


    private function reOrderRelationsCategories($cats)
    {


        // always will receive subcategories.

        $subCategoriesIds = $cats->pluck('id');
        $mainCategoriesIds = $cats->pluck('parent_id');

        $categories = Category::whereIn('id', $mainCategoriesIds)->get();

        $categories->map(function ($obj) use ($subCategoriesIds) {
            return $obj->chidren = $this->getChild($obj->id, $subCategoriesIds);
        });

        return $categories;


    }

    private function getChild($childId, $subIds)
    {
        return [
            $childId,
            $subIds
        ];

    }

    public function store(Request $request)
    {
        if ($request->user()->followers()->where('follow_id', $request->userId)->first()) {
            $request->user()->followers()->detach($request->userId);
            $message = "unfollow";
        } else {
            $request->user()->followers()->attach($request->userId);
            $message = "follow";
        }
        return response()->json([
            'status' => 200,
            'message' => $message
        ]);
    }

    public function storeCategoryFollow(Request $request)
    {
//        $request->user()->categoryFollows()->syncWithoutDetaching($request->categoriesIds);


        foreach ($request->categoriesIds as $categoryId):
            if (in_array($categoryId, collect($request->user()->categoryFollows->pluck('id'))->toArray())) {
                $request->user()->categoryFollows()->detach($categoryId);
            }else{
                $request->user()->categoryFollows()->attach([$categoryId]);
            }
        endforeach;

        return response()->json([
            'status' => 200,
            'message' => "Followed"
        ]);
    }
}
