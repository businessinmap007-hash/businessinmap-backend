<?php

namespace App\Http\Controllers\Api\V1;

use App\Company;
use App\Http\Resources\Posts\PostResource;
use App\Models\Job;
use App\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Images;

class JobController extends Controller
{

    public function index(Request $request)
    {

        /**
         * Set Default Value For Skip Count To Avoid Error In Service.
         * @ Default Value 15...
         */
        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 10;
        endif;
        /**
         * SkipCount is Number will Skip From Array
         */
        $skipCount = $request->skipCount;


        $currentPage = $request->get('page', 1); // Default to 1

        $query = Job::orderBy('created_at', 'desc')
            ->select();


        if (isset($request->categoryId) && $request->categoryId != "") {
            $query->whereCategoryId($request->categoryId);
        } else {
            $query->whereCategoryId(1);
        }
        if (isset($request->filterby) && $request->filterby == 'date') {
            $query->orderBy('created_at', 'desc');
        }
        /**
         * @@ Skip Result Based on SkipCount Number And Pagesize.
         */
        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        $jobs = $query->get();

        /**
         * Return Data Array
         */


        return PostResource::collection($jobs)->additional(['status' => 200, 'message' => "Message"]);
//        return response()->json([
//            'status' => 200,
//            'data' => $jobs
//        ]);

    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function saveProduct(Request $request)
    {

        /**
         * @ GET company...
         */
        $company = Company::whereId($request->companyId)->first();

        if (!$company)
            return response()->json(['status' => false, 'message' => 'Company Not Found in System']);

        $product = new Product;
        $product->name = $request->name;
        $product->price = $request->price;
        $product->description = $request->description;

        if ($request->hasFile('image')):
            $product->image = $request->root() . '/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
        endif;


        if ($company->products()->save($product)) {
            return response()->json([
                'status' => true,
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }


    }


    public function productsList(Request $request)
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

        $query = Product::with('company')
            ->where('company_id', $request->companyId)
            ->orderBy('created_at', 'desc')
            ->select();

        /**
         * @ If item Id Exists skipping by it.
         */
        if ($itemId) {
            $query->where('id', '<=', $itemId);
        }

        /**
         * @@ Skip Result Based on SkipCount Number And Pagesize.
         */
        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        $products = $query->get();

        /**
         * Return Data Array
         */

        return response()->json([
            'status' => true,
            'data' => $products
        ]);

    }


    public function update(Request $request)
    {
        $model = Product::whereId($request->productId)->first();

        $model->name = $request->name;
        $model->price = $request->price;

        $model->description = $request->description;
        if ($request->hasFile('image')):
            $model->image = $request->root() . '/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
        endif;
        if ($model->save()) {
            return response()->json([
                'status' => true,
                'data' => $model
            ]);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @ Delete
     */

    public function delete(Request $request)
    {
        $model = Product::whereId($request->productId)->first();

        if (!$model) {
            return response()->json([
                'status' => false,
                'message' => 'هذا المنتج غير موجود'
            ]);
        }

        if ($model->delete()) {
            return response()->json([
                'status' => true,
                'message' => 'لقد تم حذف المنتج بنجاح'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'لقد حدث خطأ, من فضلك حاول مرة آخرى'
            ]);
        }


    }

}
