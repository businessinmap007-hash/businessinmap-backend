<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Products\StoreProductRequest;
use App\Http\Requests\Admin\Products\UpdateProductRequest;
use App\Models\Bus;
use App\Models\Category;
use App\Models\Image;
use App\Models\Location;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Validator;
use App\Http\Helpers\Images;

class ProductController extends Controller
{


    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/uploads/';
    }

    /**
     * Display a listing of User.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        // return users to non-permissions page if doesn't have it.
        if (!Gate::allows('list_products')) {
            return abort(401);
        }

        $query = Product::orderBy('created_at', 'desc');
        if(!auth()->user()->isUserAdmin()){
            $query->where('user_id', auth()->id());
        }

        $results = $query->get();

        return view('admin.products.index', compact('results'));

    }


    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('add_products')) {
            return abort(401);
        }

        /**
         * @@ Get List Of Countries From location table.
         * @@ Array Returned.
         */
        $countries = Location::country()->get();

        $categories = Category::parentCategory()->get();

        return view('admin.products.create')->with(compact('categories', 'countries'));

    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreProductRequest $request)
    {
        if (!Gate::allows('add_products')) {
            return abort(401);
        }


        \DB::beginTransaction();
        try {
            $inputs = $request->except('_token');
            $categoryId = $request->subCategory > 0 ? $request->subCategory : $request->mainCategory;
            $inputs['category_id'] = $categoryId;
            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . Images::imageUploader($request->file('image'), $this->public_path);
            $product = auth()->user()->products()->create($inputs);

            if ($product)
                foreach ($request->images as $image):
                    if (!$image)
                        continue;
                    $attachment = new Image();
                    $attachment->image = $this->public_path . Images::imageUploader($image, $this->public_path);
                    $product->images()->save($attachment);
                endforeach;
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something went wrong!', null);
        }
        return returnedResponse(200, 'تم إضافة المنتج بنجاح', null, route('products.index'));
    }

    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Gate::allows('list_products')) {
            return abort(401);
        }

        $result = Product::findOrFail($id);

        return view('admin.products.show', compact('result'));
    }


    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        if (!Gate::allows('edit_products')) {
            return abort(401);
        }

        $categories = Category::get();
        $result = Product::findOrFail($id);
        return view('admin.products.edit', compact('result', 'categories'));
    }

    /**
     * Update User in storage.
     *
     * @param  \App\Http\Requests\UpdateUsersRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateProductRequest $request, $id)
    {


        if (!Gate::allows('edit_products')) {
            return abort(401);
        }

        $product = Product::findOrFail($id);
        $inputs = $request->except('_token', '_method');

        \DB::beginTransaction();
        try {

            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . UploadImage::uploadMainImage($request, 'image', $this->public_path);
            $product->fill($inputs)->update($inputs);

            if (isset($request->images)):
                foreach ($request->images as $image):
                    if (!$image)
                        continue;
                    $attachment = new Image();
                    $filename = time() . '-' . $image->getClientOriginalName();
                    $image->move($this->public_path, $filename);
                    $attachment->image = $this->public_path . $filename;
                    $product->images()->save($attachment);
                endforeach;
            endif;

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something was wrong!', null);
        }
        return returnedResponse(200, 'Product has been added successfully.', null, route('products.index'), ['type' => 'update']);

    }

    /**
     * Remove User from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('delete_users')) {
            return abort(401);
        }
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $id
            ]
        ]);
    }


    public function deleteImage(Request $request)
    {
        $image = Image::findOrFail($request->imageId);
        if ($image->delete())
            return response()->json(['status' => true]);
    }
}
