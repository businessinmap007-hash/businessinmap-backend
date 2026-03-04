<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Location\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LocationController extends Controller
{

    /**
     * @var Category
     */

    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/uploads/';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {


        /**
         * Get all Categories
         */
        $query = Location::orderBy('created_at', 'desc');

        if ($request->has('country') && $request->get('country') != '')
            $query->whereParentId($request->get('country'));
        else
            $query->country();

        $locations = $query->get();

        ## SHOW CATEGORIES LIST VIEW WITH SEND CATEGORIES DATA.
        return view('admin.locations.index')
            ->with(compact('locations'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $locations = Location::country()->get();
        return view('admin.locations.create')->with(compact('locations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLocationRequest $request)
    {

        $inputs = $request->all();
        $location = Location::create($inputs);
        if ($location) {
            session()->flash('success', __('trans.addingSuccess', ['itemName' => __('trans.category')]));
            return redirect(route('locations.index'));
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $location = Location::findOrFail($id);
        $locations = Location::country()->whereNotIn('id', [$id])->get();
        return view('admin.locations.edit')->with(compact('location', 'locations'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $category->{'name:ar'} = $request->name_ar;
        $category->{'name:en'} = $request->name_en;
        $category->parent_id = $request->parentId;
        if ($category->save()) {

            session()->flash('success', __('trans.editSuccess', ['itemName' => __('trans.category')]));
            return redirect(route('categories.index'));
        }


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = Location::findOrFail($id);
        if ($model->delete())
            return response()->json(['status' => true, 'message' => "لقد تم حذف القسم بنجاح.", 'data' => ['id' => $id]]);
    }


    public function suspend(Request $request)
    {
        $model = Category::findOrFail($request->id);
        $model->is_active = $request->type;
        if ($model->save()) {
            return response()->json([
                'status' => true,
                'id' => $request->id,
                'type' => $request->type
            ]);
        }
    }


    public function categoryTypes(Request $request)
    {


        $type = $request->type;


        if ($type == 0) {
            $categories = Category::whereIn('type', [0, 2])->whereIsActive(1)->get();
        } elseif ($type == 1) {
            $categories = Category::whereIn('type', [1, 2])->whereIsActive(1)->get();

        } else {
            $categories = Category::where('type', 2)->whereIsActive(1)->get();
        }


        return response()->json([
            'status' => true,
            'data' => $categories
        ]);

    }
}
