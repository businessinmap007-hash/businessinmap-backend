<?php

namespace App\Http\Controllers\Admin;

use App\Models\Banner;
use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
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

        $results = Coupon::orderBy('created_at', 'desc')->get();

        // return $results;
        return view('admin.coupon.index', compact('results'));

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
        return view('admin.coupon.create');

    }

    /**
     * Store a newly created User in storage.
     *
     * @param \App\Http\Requests\StoreUsersRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $inputs = $request->except('_token');
        Coupon::create(array_merge($inputs, array('expire_at' => Carbon::parse($request->expire_at))));

        return returnedResponse(200, 'تم إضافة كود الخصم بنجاح', null, route('coupons.index'));
    }

    /**
     * Show the form for editing User.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */

    public function edit($id)
    {
        $coupon = Coupon::findOrFail($id);
        return view('admin.coupon.edit', compact('coupon'));
    }

    /**
     * Update User in storage.
     *
     * @param \App\Http\Requests\UpdateUsersRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {



        $coupon = Coupon::findOrFail($id);
        $inputs = $request->except('_token', '_method');

        \DB::beginTransaction();
        try {
            $coupon->fill($inputs)->update($inputs);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            return returnedResponse(400, 'Something was wrong!', null);
        }
        return returnedResponse(200, 'لقد تم بيانات كود الخصم بنجاح', null, route('coupons.index'), ['type' => 'update']);

    }

    /**
     * Remove User from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {



        \DB::beginTransaction();
        try {
            $coupon = Coupon::findOrFail($id);
            $coupon->delete();
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
