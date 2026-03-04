<?php

namespace App\Http\Controllers\AdminV2\Settings;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin_v2.settings.index');
    }
}
