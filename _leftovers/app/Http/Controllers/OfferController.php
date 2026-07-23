<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Offer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index(){

        // get offers at start at date
        $products = Offer::whereDate('started_at', '>=', Carbon::today()->toDateString())
            ->whereDate('started_at', Carbon::today()->toDateString())
            ->orWhere('started_at', '<', Carbon::today()->toDateString())
            ->whereDate('ended_at', '>=', Carbon::today()->toDateString())
            ->orderBy('created_at', 'desc')->paginate(12);

        // get all countries
        $countries = Location::whereParentId(0)->get();

        // return all offers in views
        return view('offers.index' , compact('products', 'countries'));

    }
}
