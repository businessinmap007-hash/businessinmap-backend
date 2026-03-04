<?php

namespace App\Http\Controllers\Api\V1;

use App\City;
use App\CityTranslation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;

class CitiesController extends Controller
{
    public function __construct(Request $request)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);
    }

    public function index()
    {
        $cities = City::get();
        return response()->json([
            'status' => 200,
            'data' => $cities
        ]);
        
    }
}
