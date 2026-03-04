<?php

namespace App\Http\Controllers\Admin;

use App\Models\Banner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use App\Http\Helpers\Images;

class BannerController extends Controller
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
        if (!Gate::allows('list_banners')) {
            return abort(401);
        }

        $results = Banner::orderBy('created_at', 'desc')->get();

        // return $results;
        return view('admin.banners.index', compact('results'));

    }


    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('add_banners')) {
            return abort(401);
        }
        /**
         * Return Slider View.
         */
        return view('admin.banners.create');

    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Gate::allows('add_banners')) {
            return abort(401);
        }
        \DB::beginTransaction();
        try {
            $inputs = $request->except('_token');
            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . Images::imageUploader($request->file('image'), $this->public_path);
            Banner::create($inputs);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something went wrong!', null);
        }
        return returnedResponse(200, 'تم إضافة البانر الإعلاني بنجاح', null, route('banners.index'));
    }

    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        if (!Gate::allows('edit_banners')) {
            return abort(401);
        }

        $result = Banner::findOrFail($id);
        return view('admin.banners.edit', compact('result'));
    }

    /**
     * Update User in storage.
     *
     * @param  \App\Http\Requests\UpdateUsersRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {


        if (!Gate::allows('edit_banners')) {
            return abort(401);
        }

        $banner = Banner::findOrFail($id);
        $inputs = $request->except('_token', '_method');

        \DB::beginTransaction();
        try {

            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . UploadImage::uploadMainImage($request, 'image', $this->public_path);
            $banner->fill($inputs)->update($inputs);


            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something was wrong!', null);
        }
        return returnedResponse(200, 'لقد تم تعديل البانر الإعلاني بنجاح', null, route('banners.index'), ['type' => 'update']);

    }

    /**
     * Remove User from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('delete_banners')) {
            return abort(401);
        }

        \DB::beginTransaction();
        try {
            $slider = Banner::findOrFail($id);
            $slider->delete();
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something was wrong!', null);
        }


        return response()->json([
            'status' => true,
            'data' => [
                'id' => $id
            ]
        ]);
    }


}
