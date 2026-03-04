<?php

namespace App\Http\Controllers\Admin;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PostController extends Controller
{
    public function index(Request $request)
    {

        $query = Post::whereType('post')->orderBy('created_at', 'desc');

        /**
         * @@ check if businessId exist to get only sponsors
         *@@ else get all.
         */
        if (isset($request->businessId) && $request->businessId != "")
            $query->whereUserId($request->businessId);


        $posts = $query->get();

        // Return View Sponsors List.
        return view('admin.posts.index', compact('posts'));
    }


    public function jobs(Request $request)
    {
        $query = Post::whereType('job')->orderBy('created_at', 'desc');

        /**
         * @@ check if businessId exist to get only sponsors
         *@@ else get all.
         */
        if (isset($request->businessId) && $request->businessId != "")
            $query->whereUserId($request->businessId);


        $posts = $query->get();

        // Return View Sponsors List.
        return view('admin.jobs.index', compact('posts'));
    }
}
