<?php

namespace App\Http\Controllers\AdminV2\Ads;

use App\Http\Controllers\Controller;

class AdController extends Controller
{
    public function index()
    {
        return view('admin_v2.ads.index');
    }
}
