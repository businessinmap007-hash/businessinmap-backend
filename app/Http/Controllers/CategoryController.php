<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __invoke()
    {
        $allCategories = Category::parentCategory()->get();
        return view('categories.index', compact('allCategories'));
    }

    public function index()
    {
        $allCategories = Category::parentCategory()->get();
        return view('categories.index', compact('allCategories'));
    }
}
