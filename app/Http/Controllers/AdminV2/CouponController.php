<?php

namespace App\Http\Controllers\AdminV2\Coupons;

use App\Http\Controllers\Controller;

class CouponController extends Controller
{
    public function index()
    {
        return view('admin_v2.coupons.index');
    }
}
