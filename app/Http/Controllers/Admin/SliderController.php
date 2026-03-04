<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Sliders\StoreSliderRequest;
use App\Models\Slider;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use App\Http\Helpers\Images;

class SliderController extends Controller
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
        if (!Gate::allows('list_sliders')) {
            return abort(401);
        }

        $results = Slider::orderBy('created_at', 'desc')->get();

        // return $results;
        return view('admin.sliders.index', compact('results'));

    }


    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('add_sliders')) {
            return abort(401);
        }
        /**
         * Return Slider View.
         */
        return view('admin.sliders.create');

    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSliderRequest $request)
    {
        if (!Gate::allows('add_sliders')) {
            return abort(401);
        }
        \DB::beginTransaction();
        try {
            $inputs = $request->except('_token');
            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . Images::imageUploader($request->file('image'), $this->public_path);
            Slider::create($inputs);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something went wrong!', null);
        }
        return returnedResponse(200, 'تم إضافة المعرض بنجاح', null, route('sliders.index'));
    }

    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Gate::allows('list_sliders')) {
            return abort(401);
        }
        $result = Slider::findOrFail($id);

        return view('admin.sliders.show', compact('result'));
    }


    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        if (!Gate::allows('edit_sliders')) {
            return abort(401);
        }

        $result = Slider::findOrFail($id);
        return view('admin.sliders.edit', compact('result'));
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


        if (!Gate::allows('edit_sliders')) {
            return abort(401);
        }

        $slider = Slider::findOrFail($id);
        $inputs = $request->except('_token', '_method');

        \DB::beginTransaction();
        try {

            if ($request->hasFile('image'))
                $inputs['image'] = $this->public_path . UploadImage::uploadMainImage($request, 'image', $this->public_path);
            $slider->fill($inputs)->update($inputs);


            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something was wrong!', null);
        }
        return returnedResponse(200, 'لقد تم تعديل المعرض بنجاح', null, route('sliders.index'), ['type' => 'update']);

    }

    /**
     * Remove User from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('delete_sliders')) {
            return abort(401);
        }

        \DB::beginTransaction();
        try {
            $slider = Slider::findOrFail($id);
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
