<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sponsor;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SponsorController extends Controller
{
    public function index(Request $request)
    {

        $query = Sponsor::orderBy('created_at', 'desc');

        /**
         * @@ check if businessId exist to get only sponsors
         *@@ else get all.
         */
        if (isset($request->businessId) && $request->businessId != "")
            $query->whereUserId($request->businessId);


        $sponsors = $query->get();

        // Return View Sponsors List.
        return view('admin.sponsors.index', compact('sponsors'));
    }
}
