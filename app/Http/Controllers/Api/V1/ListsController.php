<?php

namespace App\Http\Controllers\Api\V1;

use App\Battery;
use App\Brand;
use App\BranchTranslation;
use App\Carmodel;
use App\Models\Ad;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Companytype;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Commercetype;
use App\Cover;
use App\Models\Faq;
use App\Maintenance;
use App\Models\Product;
use App\Size;
use App\Models\User;
use App\Year;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;

class ListsController extends Controller
{
    public function __construct(Request $request)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);


    }

    public function getCities()
    {

        $cities = Location::whereIsActive(1)->get();
        return response()->json([
            'status' => 200,
            'data' => $cities
        ]);

    }

    public function getBrands()
    {
        $brands = Brand::get();
        return response()->json([
            'status' => 200,
            'data' => $brands
        ]);

    }


    public function getBranches()
    {
        $branches = Branch::whereIsActive(1)->get();

        return response()->json([
            'status' => 200,

            'data' => $branches
        ]);

    }

    public function getSizes()
    {
        $sizes = \App\Models\Size::whereIsActive(1)->get();
        return response()->json([
            'status' => 200,
            "message" => "Sizes List.",
            'data' => $sizes
        ]);

    }


    public function getBankAccounts()
    {
        $banks = Bank::get();


        return response()->json([
            'status' => 200,
            "message" => "Bank Accounts List.",
            'data' => $banks
        ]);

    }


    public function getProducts()
    {
        $products = Product::whereIsActive(1)->get();

        return response()->json([
            'status' => 200,
            'data' => $products
        ]);

    }

    public function getModels($id = 0)
    {
        $models = Carmodel::where('brand_id', $id)->get();
        return response()->json([
            'status' => 200,
            'data' => $models
        ]);

    }

    public function getMaintenances()
    {
        $maintenances = Maintenance::all();
        return response()->json([
            'status' => 200,
            'data' => $maintenances
        ]);

    }

    public function covers()
    {
        $covers = Cover::all();
        return response()->json([
            'status' => 200,
            'data' => $covers
        ]);

    }

    public function battaries()
    {
        $battaries = Battery::all();
        return response()->json([
            'status' => 200,
            'data' => $battaries
        ]);

    }

    public function sizes()
    {
        $sizes = Size::where('type', 'jants')->get();
        return response()->json([
            'status' => 200,
            'data' => $sizes
        ]);

    }

    public function cover_sizes()
    {
        $sizes = Size::where('type', 'covers')->get();
        return response()->json([
            'status' => 200,
            'data' => $sizes
        ]);

    }


    public function battary_sizes()
    {
        $sizes = Size::where('type', 'batteries')->get();
        return response()->json([
            'status' => 200,
            'data' => $sizes
        ]);

    }

    public function commercial()
    {
        $commercial = Commercetype::all();
        return response()->json([
            'status' => 200,
            'data' => $commercial
        ]);
    }

    public function getYears()
    {
        $years = Year::all();
        return response()->json([
            'status' => 200,
            'data' => $years
        ]);

    }


    public function faqs(Request $request)
    {


        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 15;
        endif;

        $skipCount = $request->skipCount;

        $currentPage = $request->get('page', 1); // Default to 1

        $query = Faq::select();


        /**
         * @ If item Id Exists skipping by it.
         */

        $query->skip($skipCount + (($currentPage - 1) * $pageSize));

        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */


        // Get Orders List Using Skip count pagination.
        $faqs = $query->get();


        return response()->json([
            'status' => 200,
            'data' => $faqs
        ]);

    }

    public function ads()
    {
        $ads = Ad::wherePosition(1)->get();
        return response()->json([
            'status' => 200,
            'data' => $ads

        ]);
    }

    public function services()
    {
        $services = \App\Models\Service::get();

        $services->map(function ($obj) {
            $obj->description = trim(preg_replace('/\s+/', ' ', $obj->description));
        });

        return response()->json([
            'status' => 200,
            'data' => $services
        ]);
    }


    public function CampaignsType()
    {
        $campaignsType = Companytype::get();
        $campaignsType->map(function ($obj) {
            $obj->description = trim(preg_replace('/\s+/', ' ', $obj->description));
        });
        return response()->json([
            'status' => 200,
            'data' => $campaignsType
        ]);
    }

    public function Campaigns($id)
    {
        $campaigns = User::whereServiceId($id)->get();
        $campaigns->map(function ($obj) {
            $obj->description = trim(preg_replace('/\s+/', ' ', $obj->description));
            $obj->image = ($obj->files->count() > 0) ? $obj->files[0]['url'] : "public/assets/images/placeholder.jpg";
        });
        return response()->json([
            'status' => 200,
            'data' => $campaigns
        ]);
    }


}
