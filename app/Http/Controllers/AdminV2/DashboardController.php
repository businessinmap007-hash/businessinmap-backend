<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Deposit;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users'      => class_exists(User::class) ? User::count() : 0,
            'categories' => class_exists(Category::class) ? Category::count() : 0,
            'products'   => class_exists(Product::class) ? Product::count() : 0,
        ];

        $openDisputesCount = Deposit::where('status', 'dispute')->count();

        return view('admin-v2.dashboard.index', compact('stats', 'openDisputesCount'));
    }
}