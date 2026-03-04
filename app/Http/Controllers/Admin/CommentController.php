<?php

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CommentController extends Controller
{
    public function index(Request $request, Post $post){

        $query = $post->comments()->with('children')->orderBy('created_at',  'desc');

        $comments = $query->get();

        return view('admin.comments.index', compact('comments', 'post'));

    }
}
