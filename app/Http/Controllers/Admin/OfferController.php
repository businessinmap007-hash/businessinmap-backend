<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Offers\OfferRequest;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use App\Http\Helpers\Images;

class OfferController extends Controller
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
        if (!Gate::allows('list_offers')) {
            return abort(401);
        }

        // Get All Offers
        $results = Offer::orderBy('created_at', 'desc')->get();

        //        return $results;
        return view('admin.offers.index', compact('results'));

    }


    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('add_offers')) {
            return abort(401);
        }

        /**
         * @@ Get List Of Countries From location table.
         * @@ Array Returned.
         */

        $products = Product::get();

        return view('admin.offers.create')->with(compact('products'));

    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(OfferRequest $request)
    {
        if (!Gate::allows('add_offers')) {
            return abort(401);
        }


        $product = Product::findOrfail($request->product_id);


        \DB::beginTransaction();
        try {

            $inputs = $request->except('_token');
            $inputs['started_at'] = date('Y-m-d H:i:s', strtotime($request->started_at));
            $inputs['ended_at'] = date('Y-m-d H:i:s', strtotime($request->ended_at));
            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . Images::imageUploader($request->file('image'), $this->public_path);
            $product->offers()->create($inputs);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something went wrong!', null);
        }
        return returnedResponse(200, 'تم إضافة المنتج بنجاح بنجاح', null, route('offers.index'));
    }

    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Gate::allows('list_offers')) {
            return abort(401);
        }

        $result = Offer::findOrFail($id);

        return view('admin.offers.show', compact('result'));
    }


    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        if (!Gate::allows('edit_offers')) {
            return abort(401);
        }
        $products = Product::get();
        $result = Offer::findOrFail($id);
        return view('admin.offers.edit', compact('result', 'products'));
    }

    /**
     * Update User in storage.
     *
     * @param  \App\Http\Requests\UpdateUsersRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(OfferRequest $request, $id)
    {


        if (!Gate::allows('edit_offers')) {
            return abort(401);
        }

        $offer = Offer::findOrFail($id);
        $inputs = $request->except('_token', '_method');

        \DB::beginTransaction();
        try {

            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . UploadImage::uploadMainImage($request, 'image', $this->public_path);
            $inputs['started_at'] = date('Y-m-d H:i:s', strtotime($request->started_at));
            $inputs['ended_at'] = date('Y-m-d H:i:s', strtotime($request->ended_at));

            $offer->fill($inputs)->update($inputs);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something was wrong!', null);
        }
        return returnedResponse(200, 'Product has been added successfully.', null, route('offers.index'), ['type' => 'update']);

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
        $user = Offer::findOrFail($id);
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
