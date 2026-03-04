<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Category\StoreCategoryRequest;
use App\Models\Category;
use App\Models\Option;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Validator;
use App\Http\Helpers\Images;


class CategoriesController extends Controller
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
        $allCategories = Category::parentCategory()->orderBy('reorder', 'asc')->get();

        ## SHOW CATEGORIES LIST VIEW WITH SEND CATEGORIES DATA.
        return view('admin.categories.index')
            ->with(compact('allCategories'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::parentCategory()->get();
        $options = Option::get();
        return view('admin.categories.create')->with(compact('categories', 'options'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCategoryRequest $request)
    {


        $inputs = $request->all();


        $inputs['parent_id'] = isset($request->parentId) ? $request->parentId : 0;



        if ($request->hasFile('image'))
            $inputs['image'] = $this->public_path . Images::imageUploader($request->file('image'), $this->public_path);

        if ($category = Category::create($inputs)) :
            if (isset($request->options) && count($request->options) > 0):

                $category->options()->attach($request->options);
//                foreach ($request->options as $option):
//                    Option::create(array('name' => $option, 'category_id' => $category->id));
//                endforeach;
            endif;

            session()->flash('success', __('trans.addingSuccess', ['itemName' => __('trans.category')]));
            return redirect(route('categories.index'));
        endif;

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $category = Category::findOrFail($id);
        $categories = Category::parentCategory()->get();

        $options = Option::get();

        return view('admin.categories.edit')->with(compact('category', 'categories', 'options'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {





        $category = Category::findOrFail($id);

        $category->{'name:ar'} = $request->name_ar;
        $category->{'name:en'} = $request->name_en;

        if (isset($request->per_month))
            $category->per_month = $request->per_month;


        if (isset($request->reorder))
            $category->reorder = $request->reorder;

        if (isset($request->per_year))
            $category->per_year = $request->per_year;


        $category->parent_id = $request->parentId;

        if ($request->hasFile('image'))
            $category->image = $this->public_path . Images::imageUploader($request->file('image'), $this->public_path);

        if (isset($request->options) && count($request->options) > 0):


            $category->options()->sync($request->options);

//            $category->options->each->delete();
//
//            foreach ($request->options as $option):
//                Option::create(array('name' => $option, 'category_id' => $category->id));
//            endforeach;
        endif;

        if ($category->save()) {

            session()->flash('success', __('trans.editSuccess', ['itemName' => __('trans.category')]));
            return redirect()->back();
        }


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = Category::findOrFail($id);
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


    public function getSelectedCategories(Request $request)
    {

        $categories = Category::whereParentId($request->categoryId)->get();
        return response()->json($categories);


    }
}
