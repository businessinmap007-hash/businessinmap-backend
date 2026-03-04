<?php

namespace App\Http\Controllers\Admin;

use App\Agency;
use App\Branch;
use App\Brand;
use App\Company;
use App\Models\Agenttype;
use App\Models\City;
use App\Models\Companytype;
use App\Models\Country;
use App\Models\Image;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Silber\Bouncer\Database\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsersRequest;
use App\Http\Requests\Admin\UpdateUsersRequest;
use Illuminate\Support\Facades\Mail;
use App\Http\Helpers\Images;
use Validator;

class UsersController extends Controller
{


    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/users/';
    }


    /**
     * Display a listing of User.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {


        // return $users = User::whereIs('customer_services')->get();
        if (!Gate::allows('list_users')) {
            return abort(401);
        }


        $users = User::orderBy('created_at', 'desc')->whereHas('roles', function ($q) {
            $q->where('name', '!=', 'owner');

        })->whereType('admin')->whereisNot('customer_services')->get();
        return view('admin.users.index', compact('users'));

    }


    /**
     * Display a listing of User.
     *
     * @return \Illuminate\Http\Response
     */

    public function usersManagers(Request $request)
    {
        if (!Gate::allows('admins_management')) {
            return abort(401);
        }


        $query = User::whereHas('roles');

        $query->where(['is_active' => 1]);


        if (isset($request->city) && $request->city != 'all') {
            $query->where(['city_id' => $request->city]);
        }

        if (isset($request->status) && $request->status != 'all') {
            $status = ($request->status == 'active') ? 1 : 0;
            $query->where(['is_suspend' => $status]);
        }

        if (isset($request->name) && $request->name != '') {

            $query->where('username', 'LIKE', "%$request->name%");
        }


        if (isset($request->phone) && $request->phone != '') {

            $query->where(['phone' => $request->phone]);
        }


        $query->where('is_user', 0);

        $users = $query->get();


        $suspendUser = User::where('is_suspend', 1)->whereIsUser(0)->whereIsActive(1)->whereHas('roles')->count();


        ## SHOW CATEGORIES LIST VIEW WITH SEND CATEGORIES DATA.
        return view('admin.users.managers.index', compact('users', 'suspendUser'));


    }


    public function getUsersByCity(Request $request)
    {
        if ($request->value) {
            $url = htmlspecialchars_decode($request->url);
            session()->put('UserAppcity', $request->value);
            return response()->json([
                'status' => true,
                'url' => $url
            ]);
        }

    }

    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('admins_management')) {
            return abort(401);
        }

        $roles = Role::get();

        $roles = $roles->reject(function ($q) {
            return $q->name == 'owner' || $q->name === '*';
        });

//        $roles = Role::get()->pluck('name', 'name');

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        if (!Gate::allows('admins_management')) {
            return abort(401);
        }

        $inputs = $request->all();
        $inputs['type'] = 'admin';
        $user = User::create(array_merge($inputs, array('api_token' => str_random(120))));
        foreach ($request->input('roles') as $role) {
            if ($role && $role != "") {
                $user->assign($role);
            }
        }
        return returnedResponse(200, "لقد تم تسجيل المستخدم بنجاح", null, route('users.index'));


    }

    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!Gate::allows('list_users')) {
            return abort(401);
        }
        $roles = Role::get();

        $user = User::findOrFail($id);

        return view('admin.users.show', compact('user', 'roles'));
    }


    /**
     * Show the form for editing User.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        if (!Gate::allows('admins_management')) {
            return abort(401);
        }
//      $roles = Role::get()->pluck('name', 'name');
        $roles = Role::get();

//        if (!auth()->user()->roles()->where('name', 'owner')->first()) {
//            $roles = $roles->reject(function ($q) {
//                return $q->name == 'owner';
//            });
//        }
//
//        $roles = Role::get();

        $roles = $roles->reject(function ($q) {
            return $q->name == 'owner';
        });


        $user = User::findOrFail($id);


        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update User in storage.
     *
     * @param  \App\Http\Requests\UpdateUsersRequest $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUsersRequest $request, $id)
    {
        if (!Gate::allows('admins_management')) {
            return abort(401);
        }


        $user = User::findOrFail($id);


        $inputs = $request->all();
        $user->fill($inputs)->update($inputs);


        /**
         * @ Store Image With Image Intervention.
         */

        if ($request->input('roles')) {
            foreach ($user->roles as $role) {
                $user->retract($role);
            }

            foreach ($request->input('roles') as $role) {
                $user->assign($role);
            }
        }


        if (auth()->id() == $user->id) {
            session()->flash('success', "لقد تم تعديل الملف الشخصى بنجاح");
            return redirect()->route('user.profile');
        }


        session()->flash('success', "لقد تم تعديل المستخدم بنجاح");
        return redirect()->route('users.index');
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


    /**
     * Remove User from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {


        if (!Gate::allows('admins_management')) {
            return abort(401);
        }
        $user = User::findOrFail($request->id);
        $user->delete();

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $request->id
            ]
        ]);
    }

    public function suspendUser(Request $request)
    {


        if (!Gate::allows('users_manage')) {
            return abort(401);
        }


        if ($request->type == 0 && $request->reason == "") {
            return response()->json([
                'status' => false,
            ]);
        }


        $user = User::findOrFail($request->id);

        $user->suspend_at = !$request->type ? Carbon::now() : null;


        $user->suspend_reason = $request->reason;

        if ($user->save()) {
            return response()->json([
                'status' => true,
                'message' => "لقد تم حظر المستخدم بنجاح",
                'id' => $request->id,
                'type' =>  $user->suspend_at
            ]);
        }

    }

    /**
     * Delete all selected User at once.
     *
     * @param Request $request
     */
    public function massDestroy(Request $request)
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        if ($request->input('ids')) {
            $entries = User::whereIn('id', $request->input('ids'))->get();

            foreach ($entries as $entry) {
                $entry->delete();
            }
        }
    }


    function filter(Request $request)
    {

        $name = $request->keyName;

        $page = $request->pageSize;

        ## GET ALL CATEGORIES PARENTS
        $query = User::with('roles')->select();
        // $categories = Category::paginate($pageSize);


        if ($name != '') {
            $query->where('name', 'like', "%$name%");
        }

        $query->orderBy('created_at', 'DESC');
        $users = $query->paginate(($page) ?: 10);

        if ($name != '') {
            $users->setPath('users?name=' . $name);
        } else {
            $users->setPath('users');
        }


        if ($request->ajax()) {
            return view('admin.users.load', ['users' => $users])->render();
        }
        ## SHOW CATEGORIES LIST VIEW WITH SEND CATEGORIES DATA.
        return view('admin.users.index')
            ->with(compact('users'));
    }


    public function profile()
    {

//        if (request('profileId')) {
//            $user = User::whereId(request('profileId'))->first();
//            if (!$user)
//                return abort(404);
//        } else {
        $user = auth()->user();
//        }

        return view('admin.users.profile')
            ->with(compact('user'));
    }


    /**
     * @param $request
     * @return array
     */
    private function postData($request)
    {
        return [
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'password_confirmation' => $request->password_confirmation
        ];
    }

    /**
     * @return array
     */
    private function valRules()
    {
        return [
            'name' => 'required',
            'username' => 'required|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required',
            'password_confirmation' => 'required|same:password'
        ];
    }

    /**
     * @return array
     */
    private function valMessages()
    {
        return [
            'name.required' => trans('global.field_unique_required'),
            'name.unique' => trans('global.field_required'),
            'email.required' => trans('global.field_required'),
            'email.unique' => trans('global.unique_email'),
            'phone.required' => trans('global.field_required'),
            'phone.unique' => trans('global.unique_phone'),
            'password.required' => trans('global.field_required'),
            'password_confirmation.required' => trans('global.field_required'),
            'password_confirmation.same' => trans('global.password_not_confirmed'),
        ];
    }


    public function getProviders()
    {

        $users = User::whereIsUser(2)->get();
        return view('admin.users.providers.index')->with(compact('users'));
    }


    public function getCampaigns()
    {
        $users = User::whereIsUser(1)->get();
        return view('admin.users.campaigns.index')->with(compact('users'));

    }


    public function getAgents()
    {
        $users = User::whereIsUser(3)->get();
        return view('admin.users.agents.index')->with(compact('users'));

    }


    public function getProviderDetails($id)
    {

        $user = User::whereId($id)->first();
        if (!$user)
            abort('404');
        return view('admin.users.providers.details')->with(compact('user'));
    }


    public function getCampaignDetails($id)
    {
        $user = User::whereId($id)->first();
        if (!$user)
            abort('404');
        return view('admin.users.campaigns.details')->with(compact('user'));

    }

    public function getAgentDetails($id)
    {
        $user = User::whereId($id)->first();
        if (!$user)
            abort('404');
        return view('admin.users.agents.details')->with(compact('user'));

    }


    public function createProvider()
    {

        $services = Service::orderBy('created_at', 'desc')->get();
        return view('admin.users.providers.create')->with(compact('services'));
    }


    public function createCampaign()
    {
        $campaignTypes = Companytype::orderBy('created_at', 'desc')->get();
        $countries = Country::orderBy('created_at', 'desc')->get();
        return view('admin.users.campaigns.create')->with(compact('campaignTypes', 'countries'));

    }


    public function createAgent()
    {
        $countries = Country::orderBy('created_at', 'desc')->get();
        $agentstypes = Agenttype::orderBy('created_at', 'desc')->get();
        return view('admin.users.agents.create')->with(compact('countries', 'agentstypes'));

    }


    public function editAgent($id)
    {

        $agent = User::where(['is_user' => 3, 'id' => $id])->first();
        if (!$agent)
            return abort('404');

        $countries = Country::orderBy('created_at', 'desc')->get();
        $agentstypes = Agenttype::orderBy('created_at', 'desc')->get();
        return view('admin.users.agents.edit')->with(compact('countries', 'agentstypes', 'agent'));

    }


    public function editClient($id)
    {

        $client = User::where(['is_user' => 4, 'id' => $id])->first();
        if (!$client)
            return abort('404');

        return view('admin.users.clients.edit')->with(compact('client'));

    }


    public function editCampaign($id)
    {

        $campaign = User::where(['is_user' => 1, 'id' => $id])->first();
        if (!$campaign)
            return abort('404');

        $countries = Country::orderBy('created_at', 'desc')->get();
        $campaignTypes = Companytype::orderBy('created_at', 'desc')->get();
        return view('admin.users.campaigns.edit')->with(compact('countries', 'campaignTypes', 'campaign'));

    }


    public function editProvider($id)
    {

        $provider = User::where(['is_user' => 2, 'id' => $id])->first();
        if (!$provider)
            return abort('404');

        $countries = Country::orderBy('created_at', 'desc')->get();
        $services = Service::orderBy('created_at', 'desc')->get();
        return view('admin.users.providers.edit')->with(compact('countries', 'services', 'provider'));

    }


    public function updateAgent(Request $request, $id)
    {


        $user = User::whereId($id)->first();

        // Get Input
        $postData = [
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'phone' => 'required|unique:users,phone,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('trans.field_required'),
            'phone.unique' => trans('trans.unique_phone'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);

            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;

            $inputs['requirements:ar'] = $request->requirements_ar;
            $inputs['requirements:en'] = $request->requirements_en;

            $inputs['address:ar'] = $request->address_ar;
            $inputs['address:en'] = $request->address_en;

            $inputs['description:ar'] = $request->description_ar;
            $inputs['description:en'] = $request->description_en;

            $inputs['service_id'] = $request->agentType;
            $inputs['seats_no'] = $request->root_type;
            $inputs['permit_no'] = $request->activityType;

            if ($request->activityType == 1)
                $inputs['mina_locations'] = $request->dateFrom . ',' . $request->dateTo;
            else
                $inputs['mina_locations'] = $request->weeks_no;


            $inputs['is_active'] = 1;
            $inputs['is_user'] = 3;

            $user->fill($inputs);

            $user->save();
            if ($user) {
                for ($i = 1; $i <= 6; $i++) {
                    if ($request->hasFile('file' . $i)):
                        $file = $request->file('file' . $i);
                        if (!$file)
                            continue;
                        $attachment = new Image();
                        $filename = time() . '-' . $file->getClientOriginalName();
                        $file->move(public_path($this->public_path), $filename);
                        $attachment->url = request()->root() . '/public/' . $this->public_path . $filename;
                        $attachment->ip = $file->getClientMimeType();
                        $user->files()->save($attachment);
                    endif;
                }
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.agents')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }
    }


    public function updateClient(Request $request, $id)
    {


        $user = User::whereId($id)->first();

        // Get Input
        $postData = [
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        // Declare Validation Messages
        $valMessages = [
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);

            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;


            $user->fill($inputs);

            $user->save();
            if ($user) {

                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.clients')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }
    }


    public function updateCampaign(Request $request, $id)
    {


        $user = User::whereId($id)->first();
        // Get Input
        $postData = [
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'phone' => 'required|unique:users,phone,' . $id,
            'email' => 'required|email|unique:users,email,' . $id,
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('trans.field_required'),
            'phone.unique' => trans('trans.unique_phone'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);
            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;

            $inputs['mina_locations'] = serialize($request->campaign_location_minaa);

            $user->fill($inputs);
            $user->save();
            if ($user) {
                for ($i = 1; $i <= 6; $i++) {
                    if ($request->hasFile('file' . $i)):
                        $file = $request->file('file' . $i);
                        if (!$file)
                            continue;
                        $attachment = new Image();
                        $filename = time() . '-' . $file->getClientOriginalName();
                        $file->move(public_path($this->public_path), $filename);
                        $attachment->url = request()->root() . '/public/' . $this->public_path . $filename;
                        $attachment->ip = $file->getClientMimeType();
                        $user->files()->save($attachment);
                    endif;
                }
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.campaigns')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }

    }


    public function updateProvider(Request $request, $id)
    {


        $user = User::whereId($id)->first();
        // Get Input
        $postData = [
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'phone' => 'required|unique:users,phone,' . $id,
            'email' => 'required|email|unique:users,email,' . $id,
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('trans.field_required'),
            'phone.unique' => trans('trans.unique_phone'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);
            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;

            $user->fill($inputs);
            $user->save();
            if ($user) {
                for ($i = 1; $i <= 6; $i++) {
                    if ($request->hasFile('file' . $i)):
                        $file = $request->file('file' . $i);
                        if (!$file)
                            continue;
                        $attachment = new Image();
                        $filename = time() . '-' . $file->getClientOriginalName();
                        $file->move(public_path($this->public_path), $filename);
                        $attachment->url = request()->root() . '/public/' . $this->public_path . $filename;
                        $attachment->ip = $file->getClientMimeType();
                        $user->files()->save($attachment);
                    endif;
                }
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.providers')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }

    }


    public function createClient()
    {
        return view('admin.users.clients.create');

    }


    public function postProvider(Request $request)
    {

        // Get Input
        $postData = [
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'phone' => 'required|unique:users,phone',
            'email' => 'required|email|unique:users,email',
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('trans.field_required'),
            'phone.unique' => trans('trans.unique_phone'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);
            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;
            $inputs['is_active'] = 1;
            $inputs['is_user'] = 2;
            $user = User::create($inputs);
            if ($user) {
                for ($i = 1; $i <= 6; $i++) {
                    if ($request->hasFile('file' . $i)):
                        $file = $request->file('file' . $i);
                        if (!$file)
                            continue;
                        $attachment = new Image();
                        $filename = time() . '-' . $file->getClientOriginalName();
                        $file->move(public_path($this->public_path), $filename);
                        $attachment->url = request()->root() . '/public/' . $this->public_path . $filename;
                        $attachment->ip = $file->getClientMimeType();
                        $user->files()->save($attachment);
                    endif;
                }
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.providers')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }
    }


    public function postCampaign(Request $request)
    {


        // Get Input
        $postData = [
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'phone' => 'required|unique:users,phone',
            'email' => 'required|email|unique:users,email',
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('trans.field_required'),
            'phone.unique' => trans('trans.unique_phone'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);
            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;
            $inputs['is_active'] = 1;
            $inputs['is_user'] = 1;

            $inputs['mina_locations'] = serialize($request->campaign_location_minaa);
            $user = User::create($inputs);
            if ($user) {
                for ($i = 1; $i <= 6; $i++) {
                    if ($request->hasFile('file' . $i)):
                        $file = $request->file('file' . $i);
                        if (!$file)
                            continue;
                        $attachment = new Image();
                        $filename = time() . '-' . $file->getClientOriginalName();
                        $file->move(public_path($this->public_path), $filename);
                        $attachment->url = request()->root() . '/public/' . $this->public_path . $filename;
                        $attachment->ip = $file->getClientMimeType();
                        $user->files()->save($attachment);
                    endif;
                }
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.campaigns')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }
    }


    public function postAgent(Request $request)
    {


        // Get Input
        $postData = [
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'phone' => 'required|unique:users,phone',
            'email' => 'required|email|unique:users,email',
        ];

        // Declare Validation Messages
        $valMessages = [
            'phone.required' => trans('trans.field_required'),
            'phone.unique' => trans('trans.unique_phone'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);

            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;

            $inputs['requirements:ar'] = $request->requirements_ar;
            $inputs['requirements:en'] = $request->requirements_en;

            $inputs['address:ar'] = $request->address_ar;
            $inputs['address:en'] = $request->address_en;

            $inputs['description:ar'] = $request->description_ar;
            $inputs['description:en'] = $request->description_en;

            $inputs['service_id'] = $request->agentType;
            $inputs['seats_no'] = $request->root_type;
            $inputs['permit_no'] = $request->activityType;

            if ($request->activityType == 1)
                $inputs['mina_locations'] = $request->dateFrom . ',' . $request->dateTo;
            else
                $inputs['mina_locations'] = $request->weeks_no;


            $inputs['is_active'] = 1;
            $inputs['is_user'] = 3;
            $user = User::create($inputs);
            if ($user) {
                for ($i = 1; $i <= 6; $i++) {
                    if ($request->hasFile('file' . $i)):
                        $file = $request->file('file' . $i);
                        if (!$file)
                            continue;
                        $attachment = new Image();
                        $filename = time() . '-' . $file->getClientOriginalName();
                        $file->move(public_path($this->public_path), $filename);
                        $attachment->url = request()->root() . '/public/' . $this->public_path . $filename;
                        $attachment->ip = $file->getClientMimeType();
                        $user->files()->save($attachment);
                    endif;
                }
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.agents')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }
    }


    public function postClient(Request $request)
    {


        // Get Input
        $postData = [
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'email' => $request->email,
        ];

        // Declare Validation Rules.
        $valRules = [
            'name_ar' => 'required',
            'name_en' => 'required',
            'email' => 'required|email|unique:users,email',
        ];

        // Declare Validation Messages
        $valMessages = [
            'name_ar.required' => trans('trans.field_required'),
            'name_en.required' => trans('trans.field_required'),
            'email.required' => trans('trans.field_required'),
            'email.unique' => trans('trans.email_unique'),
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        if ($valResult->passes()) {
            $inputs = $request->except('_token');
            $inputs['api_token'] = str_random(60);

            $inputs['name:ar'] = $request->name_ar;
            $inputs['name:en'] = $request->name_en;

            $inputs['is_active'] = 1;
            $inputs['is_user'] = 4;
            $user = User::create($inputs);
            if ($user) {
                return response()->json(['status' => 200, 'message' => __('trans.item_added_successfully'), 'url' => route('get.clients')]);
            } else {
                return response()->json([
                    'status' => 400,
                    "message" => "Something error..."
                ]);
            }
        } else {

            $errors = [];
            foreach ($valResult->messages()->all() as $message) {
                $errors[] = '<p>' . $message . '</p>';
            }

            return response()->json([
                'status' => 402,
                'errors' => $errors,
            ]);
        }
    }


    public function getClients()
    {
        $users = User::whereIsUser(4)->get();
        return view('admin.users.clients.index')->with(compact('users'));

    }


    function is_connected()
    {
        $connected = @fsockopen("http://arkabmaana.com", 80);
        //website, port  (try 80 or 443)
        if ($connected) {
            $is_conn = true; //action when connected
            fclose($connected);
        } else {
            $is_conn = false; //action in connection failure
        }
        return $is_conn;

    }


    public function selectCity(Request $request)
    {
        $cities = City::whereCountryId($request->countryId)->get();
        return response()->json($cities);
    }


    public function deleteImage(Request $request)
    {
        $file = Image::whereId($request->imageId)->first();
        if (!$file)
            return response()->json([
                'status' => 400,
                'message' => "Something error! try again."
            ]);

        if ($file->delete()) {
            return response()->json([
                'status' => 200,
                'imageCount' => count(auth()->user()->files)
            ]);

        }

    }


}
