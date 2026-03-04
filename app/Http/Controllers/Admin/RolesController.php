<?php

namespace App\Http\Controllers\Admin;

use App\Agency;
use App\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Database\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRolesRequest;
use App\Http\Requests\Admin\UpdateRolesRequest;
use Validator;

class RolesController extends Controller
{
    /**
     * Display a listing of Role.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
//        if (!Gate::allows('users_manage')) {
//            return abort(401);
//        }

//        $roles = Role::paginate(5);
//
//        return view('admin.roles.index', compact('roles'));


        $page = request('pageSize');
        $name = request('name');

        ## GET ALL CATEGORIES PARENTS
        $query = Role::select();
//        $categories = Category::paginate($pageSize);


        if ($name != '') {
            $query->where('name', 'like', "%$name%");
        }

        $roles = $query->paginate(($page) ?: 10);


        if ($name != '') {
            $roles->setPath('roles?name=' . $name);
        } else {
            $roles->setPath('roles');
        }


        if ($request->ajax()) {
            return view('admin.roles.load', ['roles' => $roles])->render();
        }

        ## SHOW CATEGORIES LIST VIEW WITH SEND CATEGORIES DATA.
        return view('admin.roles.index')
            ->with(compact('roles'));


    }

    /**
     * Show the form for creating new Role.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        $abilities = Ability::whereParentId(0)->get();


        $abilities = $abilities->filter(function ($q) {
            return $q->name !== '*';
        });


        return view('admin.roles.create', compact('abilities'))->render();


    }

    /**
     * Store a newly created Role in storage.
     *
     * @param  \App\Http\Requests\StoreRolesRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        $mainAbilities = collect($request->input('main_abilities'));
        $subAbilities = collect($request->input('sub_abilities'));

        $inputs =  $mainAbilities->merge($subAbilities);

        $abilites = $inputs->filter(function ($value) {
            return $value != "*";
        })->values();





        $postData = [
            'title' => $request->title,

        ];

        // Declare Validation Rules.
        $valRules = [
            'title' => 'required|unique:roles,title',

        ];

        // Declare Validation Messages
        $valMessages = [
            'title.required' => __('trans.required'),
            'title.unique' => __('trans.unique_ar_title'),


        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        // Check Validate
        if ($valResult->passes()) {



            $role = new Role;

            $role->title = $request->title;

            $role->name = strtolower(str_replace(' ', '_', $request->title));




            if ($role->save()) {


                if ($abilites->count() > 0) {
                    $role->allow($abilites);
                }

                session()->flash('success', __('trans.addingSuccess', ['itemName' => __('trans.category')]));

                return redirect(route('roles.index'));
            }




        } else {
            // Grab Messages From Validator
            $valErrors = $valResult->messages();
            // Error, Redirect To User Edit
            return redirect()->back()->withInput()
                ->withErrors($valErrors);
        }


//        return redirect()->route('admin.roles.index');
    }


    /**
     * Show the form for editing Role.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        $abilities = Ability::whereParentId(0)->get();
//        $abilities = Ability::get()->pluck('id', 'name');

        $abilities = $abilities->filter(function ($q) {
            return $q->name != '*';
        });


        $role = Role::findOrFail($id);

        return view('admin.roles.edit', compact('role', 'abilities'));
    }

    /**
     * Update Role in storage.
     *
     * @param  \App\Http\Requests\UpdateRolesRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }

        $postData = [
            'title' => $request->title,
        ];

        // Declare Validation Rules.
        $valRules = [
            'title' => 'required',

        ];

        // Declare Validation Messages
        $valMessages = [
            'title.required' => __('trans.required'),
            'title.unique' => __('trans.unique_ar_title'),

        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        // Check Validate
        if ($valResult->passes()) {


            $role = Role::findOrFail($id);

            $role->title = $request->title;
            $role->name = strtolower(str_replace(' ', '_', $request->title));

            if ($role->save()) {
                //$role->update($request->all());




                $mainAbilities = collect($request->input('main_abilities'));
                $subAbilities = collect($request->input('sub_abilities'));

                $inputs =  $mainAbilities->merge($subAbilities);

                $abilites = $inputs->filter(function ($value) {
                    return $value != "*";
                })->values();


                if ($abilites->count() > 0) {
                    foreach ($role->getAbilities() as $ability) {
                        $role->disallow($ability->id);
                    }
                    $role->allow($abilites);
                }
            }

            session()->flash('success', "لقد تم تعديل الدور  ($role->title) بنجاح");
            return redirect(route('roles.index'));

        }else{
            // Grab Messages From Validator
            $valErrors = $valResult->messages();
            // Error, Redirect To User Edit
            return redirect()->back()->withInput()
                ->withErrors($valErrors);
        }
    }


    /**
     * Remove Role from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        $role = Role::findOrFail($id);




        if ($role->users->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, لا يمكنك حذف الدور نظراً لوجود مستخدمين مشتركين فيه'
            ]);
        }


        foreach ($role->getAbilities() as $ability) {
            $role->disallow($ability->id);
        }

        if ($role->delete()) {
            return response()->json([
                "status" => true,

            ]);
        }

        //return redirect()->route('admin.roles.index');
    }


    /**
     * Remove Role from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        if (!Gate::allows('roles_manage')) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, ليس لديك الصلاحيات لحذف الادوار'
            ]);
        }
        $role = Role::findOrFail($request->id);


        if ($role->users->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, لا يمكنك حذف الدور نظراً لوجود مستخدمين مشتركين فيه'
            ]);
        }


        foreach ($role->getAbilities() as $ability) {
            $role->disallow($ability->id);
        }

        if ($role->delete()) {
            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $request->id
                ],
                'message' => 'لقد تم عمليه الحذف بنجاح'
            ]);
        }


    }


    function filter(Request $request)
    {

        $name = $request->keyName;

        $page = $request->pageSize;

        ## GET ALL CATEGORIES PARENTS
        $query = Role::select();
        // $categories = Category::paginate($pageSize);


        if ($name != '') {
            $query->where('name', 'like', "%$name%");
        }

        $query->orderBy('created_at', 'DESC');
        $roles = $query->paginate(($page) ?: 10);

        if ($name != '') {
            $roles->setPath('roles?name=' . $name);
        } else {
            $roles->setPath('roles');
        }


        if ($request->ajax()) {
            return view('admin.roles.load', ['roles' => $roles])->render();
        }
        ## SHOW CATEGORIES LIST VIEW WITH SEND CATEGORIES DATA.
        return view('admin.roles.index')
            ->with(compact('users'));
    }


    /**
     * Remove User from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function groupDelete(Request $request)
    {

        if (!Gate::allows('users_manage')) {
            return abort(401);
        }

        $ids = $request->ids;
        foreach ($ids as $id) {
            $role = Role::findOrFail($id);
            $role->delete();
        }


        return response()->json([
            'status' => true,
            'data' => [
                'id' => $request->id
            ]
        ]);
    }


    /**
     * Delete all selected Role at once.
     *
     * @param Request $request
     */
    public function massDestroy(Request $request)
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        if ($request->input('ids')) {
            $entries = Role::whereIn('id', $request->input('ids'))->get();

            foreach ($entries as $entry) {
                $entry->delete();
            }
        }
    }

    /**
     *
     */


    public function companyRoles()
    {


        $agencies = Agency::get();
        $abilities = Ability::get();
        $abilities = $abilities->filter(function ($q) {
            return $q->name != '*';
        });


        return view('admin.roles.companies.index')->with(compact('abilities', 'agencies'));
    }


    public function companyRolesCreate()
    {
        $agencies = Agency::get();
        $abilities = Ability::get();
        $abilities = $abilities->filter(function ($q) {
            return $q->name != '*';
        });
        return view('admin.roles.companies.create')->with(compact('abilities', 'agencies'));
    }


    function companyRolesEdit($id)
    {
        $agency = Agency::whereId($id)->first();

        $agencies = Agency::get();


        $allAbilities = Ability::get();
        $abilities = $agency->roles;

        $allAbilities = $allAbilities->filter(function ($q) {
            return $q->name != '*';
        })->values();


        return view('admin.roles.companies.edit')->with(compact('agency', 'abilities', 'agencies', 'allAbilities'));
    }

    public function companyRolesStore(Request $request)
    {

        $agency = Agency::whereId($request->agency)->first();


        $agency->roles()->attach($request->abilities);


        session()->flash('success', 'لقد تم حفظ البيانات بنجاح.');

        return redirect(route('company.custom.roles'));


    }

    public function companyRolesUpdate(Request $request, $id)
    {
        $agency = Agency::findOrFail($id);

        if ($agency->roles()->sync($request->abilities)) {
            session()->flash('success', __('maincp.add_role_to_user'));
            return redirect(route('company.custom.roles'));
        }


    }


    public function getRolescompany(Request $request)
    {


        if ($request->type == 'sub') {

            $company = Company::whereId($request->subCompany)->first();


            if ($company) {

                $abilities = $company->abilities;
            }

            $type = $request->type;

            $html = view('admin.roles.companies.customRoles')->with(compact('abilities', 'type'))->render();


        } else {
            $abilities = DB::table('company_role')
                ->where('company_id', $request->id)->get();

            $ids = [];
            foreach ($abilities as $ability) {
                $ids[] = $ability->ability_id;
            }

            $allAbilities = Ability::get();

            $abilities = Ability::whereIn('id', $ids)->get();


            $allAbilities = $allAbilities->filter(function ($q) {
                return $q->name != '*';
            })->values();


            $type = 'main';
            $html = view('admin.roles.companies.customRoles')->with(compact('abilities', 'allAbilities', 'type'))->render();


        }


        return response()->json([
            'status' => true,
            'html' => $html
        ]);


    }

}
