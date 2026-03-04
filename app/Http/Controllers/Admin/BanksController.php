<?php

namespace App\Http\Controllers\Admin;

use App\Models\Bank;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Http\Helpers\Images;
use Illuminate\Support\Facades\Gate;

class BanksController extends Controller
{
    /**
     * @var Category
     */

    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/banks/';
    }


    public function index()
    {
        $banks = Bank::get();
        return view('admin.banks.index')->with(compact('banks'));
    }


    public function create()
    {
        return view('admin.banks.create');
    }

    public function edit($id)
    {
        $bank = Bank::findOrFail($id);
        if (!$bank)
            return abort(404);
        return view('admin.banks.edit')->with(compact('bank'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $postData = [
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'account_name_ar' => $request->account_name_ar,
            'account_name_en' => $request->account_name_en,
            'iban_number' => $request->iban_number,

        ];

        // Declare Validation Rules.
        $valRules = [
            'name_ar' => 'required',
            'name_en' => 'required',
            'account_name_ar' => 'required',
            'account_name_en' => 'required',
            'iban_number' => 'required|unique:banks|max:100',
        ];

        // Declare Validation Messages
        $valMessages = [
            'name_ar.required' => 'اسم البنك مطلوب',
            'name_en.required' => 'اسم البنك مطلوب',
            'account_name_ar.required' => 'اسم الحساب مطلوب',
            'account_name_en.required' => 'اسم الحساب مطلوب',
            'iban_number.unique'=>"رقم الإيبان مسجل من قبل",
            'iban_number.max'=>"أقصى عدد ارقام لرقم الإيبان 100 رقم",
            'iban_number.required' => 'رقم الحساب مطلوب',
        ];

        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        // Check Validate
        if ($valResult->passes()) {


            $model = new Bank();
            $model->{'name:ar'} = $request->name_ar;
            $model->{'name:en'} = $request->name_en;
            $model->{"account_name:ar"} = $request->account_name_ar;
            $model->{"account_name:en"} = $request->account_name_en;
            $model->iban_number = $request->iban_number;
            $model->is_active = 1;


            /**
             * @ Store Image With Image Intervention.
             */

            if ($request->hasFile('image')):
                $model->image = $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
            endif;

//            return $model;

            if ($model->save()) {
                session()->flash('success', __('trans.addingSuccess',['itemName' => __('trans.bank_account')]));
                return redirect(route('banks.index'));


//                return response()->json([
//                    'status' => true,
//                    "message" => __('trans.addingSuccess',['itemName' => __('trans.bank_account')]),
//                    "url" => route('banks.index')
//                ]);

            }
        } else {
            // Grab Messages From Validator
            $valErrors = $valResult->messages();
            // Error, Redirect To User Edit
            return redirect()->back()->withInput()
                ->withErrors($valErrors);
        }

    }

    public function update(Request $request, $id)
    {

        if (!Gate::allows('users_manage')) {
            return abort(401);
        }

        $model = Bank::findOrFail($id);

        // Get Input

        $postData = [
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'account_name_ar' => $request->account_name_ar,
            'account_name_en' => $request->account_name_en,
            'iban_number' => $request->ibanNumber,

        ];

        // Declare Validation Rules.
        $valRules = [
            'name_ar' => 'required',
            'name_en' => 'required',
            'account_name_ar' => 'required',
            'account_name_en' => 'required',
            'iban_number' => 'required|max:100',
        ];

        // Declare Validation Messages
        $valMessages = [
            'name_ar.required' => 'اسم البنك مطلوب',
            'name_en.required' => 'اسم البنك مطلوب',
            'account_name_ar.required' => 'اسم الحساب مطلوب',
            'account_name_en.required' => 'اسم الحساب مطلوب',
            'iban_number.max'=>"أقصى عدد ارقام لرقم الإيبان 100 رقم",
            'iban_number.required' => 'رقم الحساب مطلوب',
        ];


        // Validate Input
        $valResult = Validator::make($postData, $valRules, $valMessages);

        // Check Validate
        if ($valResult->passes()) {
            $model->{'name:ar'} = $request->name_ar;
            $model->{'name:en'} = $request->name_en;
            $model->{"account_name:ar"} = $request->account_name_ar;
            $model->{"account_name:en"} = $request->account_name_en;
            $model->iban_number = $request->ibanNumber;

            /**
             * @ Store Image With Image Intervention...
             */

            if ($request->hasFile('image')):
                $model->image = $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
            endif;
            $model->save();
            session()->flash('success',__('trans.editSuccess',['itemName' => __('trans.bank_account')]));
            return redirect()->route('banks.index');

        } else {
            // Grab Messages From Validator
            $valErrors = $valResult->messages();
            // Error, Redirect To User Edit
            return redirect()->back()->withInput()
                ->withErrors($valErrors);
        }
    }


    public function destroy($id)
    {

        $model = Bank::whereId($id)->first();
        if (!$model) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, هذا الحساب غير موجود او ربما تم حذفه'
            ]);
        }

        if ($model->delete()) {
            return response()->json([
                'status' => true,
                'message' => 'لقد تم حذف الحساب بنجاح'
            ]);
        }


    }




    public function delete(Request $request)
    {

        $model = Product::whereId($request->id)->first();
        if (!$model) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, هذا المنتج غير موجود او ربما تم حذفه'
            ]);
        }

        if ($model->delete()) {
            return response()->json([
                'status' => true,
                'message' => 'لقد تم حذف المنتج بنجاح'
            ]);
        }


    }

    public function suspend(Request $request)
    {
        $model = Bank::findOrFail($request->id);
        $model->is_active = $request->type;
        if ($request->type == 1) {
            $message = "لقد تم فك الحظر على فرع الشركة بنحاج";
        } else {
            $message = "لقد تم حظرالشركة على مستوي النظام بنجاح";
        }

        if ($model->save()) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'id' => $request->id,
                'type' => $request->type
            ]);
        }

    }
}
