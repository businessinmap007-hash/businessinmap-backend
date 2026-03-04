<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TargetController extends Controller
{



    public function __construct()
    {
        $language = request()->headers->get('lang') ? request()->headers->get('lang') : 'ar';
        app()->setLocale($language);

    }



    public function index(Request $request)
    {


        $targets = $request->user()->targets;

        if ($request->has('keyword') && $request->get('keyword') != "") {
            $keyword = $request->get('keyword');
            $targets = $request->user()->targets()->where('name', 'LIKE', "%$keyword%")
                ->orWhere('target_id', 'LIKE', "%$keyword%")->get();
        }


        $listCategories = [];

        $mainCategories = Category::whereIn('id', $request->user()->categoryTargets->pluck('parent_id')->unique())->get();

        foreach ($mainCategories as $mainCategory):
            $listCategories[] = array(

                'id' => $mainCategory->id,
                'name' => $mainCategory->name,
                'image' => $mainCategory->image,
                'children' => $this->getChildArr($mainCategory, $request->user()->categoryTargets->pluck('id')->unique())

            );
        endforeach;

        return response()->json([
            'status' => 200,
            'data' => [
                'users' => UserResource::collection($targets),
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

    public function store(Request $request)
    {


        if ($request->user()->targets()->where('target_id', $request->userId)->first()) {
            $request->user()->targets()->detach($request->userId);
            $message = "untarget";
        } else {
            $request->user()->targets()->attach($request->userId);
            $message = "target";
        }

        return response()->json([
            'status' => 200,
            'message' => $message
        ]);
    }

    public function storeTargetCategories(Request $request)
    {


        foreach ($request->categoriesIds as $categoryId):
            if (in_array($categoryId, collect($request->user()->categoryTargets->pluck('id'))->toArray())) {
                $request->user()->categoryTargets()->detach($categoryId);
            }else{
                $request->user()->categoryTargets()->attach([$categoryId]);
            }
        endforeach;


        return response()->json([
            'status' => 200,
            'message' => "Success"
        ]);
    }
}
