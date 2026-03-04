<?php

namespace App\Http\Controllers\Admin;

use App\Models\Bus;
use App\Models\Job;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Validator;

class JobController extends Controller
{


    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/pilgrims/';
    }

    /**
     * Display a listing of User.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        // return users to non-permissions page if doesn't have it.
        if (!Gate::allows('list_users')) {
            return abort(401);
        }

        $results = Job::orderBy('created_at', 'desc')->get();
        return view('admin.jobs.index', compact('results'));

    }


    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('add_pilgrims')) {
            return abort(401);
        }

        return view('admin.jobs.create');

    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (!Gate::allows('add_pilgrims')) {
            return abort(401);
        }

        // make validation for required fields
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'company' => 'required',
            'price' => 'required',
            'closed_at' => 'required',
            'start_at' => 'required',
            'category_id' => 'required',
            'papers' => 'required',

        ]);

        if ($validator->passes()) {

            $inputs = $request->except('_token');
//            $records = preg_split('/[\r\n]+/', $request->papers, -1, PREG_SPLIT_NO_EMPTY);
//            $papers = implode($records, ',');
            $inputs['start_at'] = date('Y-m-d', strtotime($request->start_at));
            $inputs['closed_at'] = date('Y-m-d', strtotime($request->closed_at));
//            $inputs['papers'] = $papers;

            $job = Job::create($inputs);
            if ($job)
                return returnedResponse(200, 'تم إضافة وظيفة بنجاح', null, route('jobs.index'));
        } else {
            $errors = [];
            foreach ($validator->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }
            return response()->json([
                'status' => 402,
                'errors' => $validator->messages()->first(),
            ]);
        }
    }

    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Gate::allows('list_pilgrims')) {
            return abort(401);
        }

        $result = Job::findOrFail($id);

        return view('admin.jobs.show', compact('result'));
    }


    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        if (!Gate::allows('edit_pilgrims')) {
            return abort(401);
        }

        $result = Job::findOrFail($id);
        return view('admin.jobs.edit', compact('result'));
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
        if (!Gate::allows('edit_pilgrims')) {
            return abort(401);
        }

        $job = Job::findOrFail($id);

        // make validation for required fields
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'company' => 'required',
            'price' => 'required',
            'closed_at' => 'required',
            'start_at' => 'required',
            'category_id' => 'required',
            'papers' => 'required',
        ]);

        if ($validator->passes()) {
            $inputs = $request->except('_token', '_method');
            $inputs['start_at'] = date('Y-m-d', strtotime($request->start_at));
            $inputs['closed_at'] = date('Y-m-d', strtotime($request->closed_at));

            if ($job->fill($inputs)->update($inputs))
                return returnedResponse(200, 'company has been added successfully.', null, route('jobs.index'), ['type' => 'update']);
        } else {
            $errors = [];

            foreach ($validator->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $validator->messages()->first(),
            ]);
        }

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

        $model = Job::findOrFail($id);

        \DB::beginTransaction();
        try {

            $model->translations()->delete();
            $model->delete();
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something wrong!', null);
        }


        return response()->json([
            'status' => true,
            'data' => [
                'id' => $id
            ]
        ]);
    }


    public function contract($id)
    {

        $user = User::findOrFail($id);
        return view('admin.pilgrims.contract', compact('user'));

    }
}
