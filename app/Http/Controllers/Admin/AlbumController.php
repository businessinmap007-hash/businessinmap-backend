<?php

namespace App\Http\Controllers\Admin;

use App\Models\Album;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AlbumController extends Controller
{
    public function index(Request $request)
    {
        $query = Album::orderBy('created_at', 'desc');

        /**
         * @@ check if businessId exist to get only sponsors
         *@@ else get all.
         */
        if (isset($request->businessId) && $request->businessId != "")
            $query->whereUserId($request->businessId);


        $albums = $query->get();

        // Return View Sponsors List.
        return view('admin.albums.index', compact('albums'));
    }
}
