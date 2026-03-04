<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class HomeController extends Controller
{
    public function index()
    {
        $stats = [
            'businesses' => User::where('type', 'business')->count(),
            'clients'    => User::where('type', 'client')->count(),
            'total'      => User::whereIn('type', ['business', 'client'])->count(),
        ];

        return view('admin.home.index', compact('stats'));
    }
}
