<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use PDF;
use App\Models\Order;

class ReportsController extends Controller
{


    public function __construct(Request $request)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);


    }


    public function index(Request $request)
    {
        $token = ltrim($request->headers->get('Authorization'), "Bearer ");

        $user = User::whereApiToken($token)->first();


        $reportType = $request->reportType;
        $reportDate = $request->reportDate;

//        return [
//
//            $reportType,
//            $reportDate
//        ];

        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 10;
        endif;

        $skipCount = $request->skipCount;

        $currentPage = $request->get('page', 1); // Default to 1


//        $q->whereMonth('created_at', '=', date('m'));


        $query = Order::where('status', 3);

        if ($reportType == "monthly") {
            $query->whereMonth('created_at', '=', $reportDate);
        }

        if ($reportType == "daily") {


            $query->whereDate('created_at', $reportDate);
        }


        if ($user->userType() == 'company') {

            $query->whereCompanyId($user->id);
        }

        if ($user->userType() == 'client') {
            $query->whereUserId($user->id);
            $query->with('track');
        }

        if ($user->userType() == 'driver') {
            $query->with('track')->whereHas('track', function ($obj) use ($user) {
                return $obj->where('driver_id', $user->id);
            });
            $query->where('status', '!=', 0);
        }

        /**
         * @ If item Id Exists skipping by it.
         */

        $query->skip($skipCount + (($currentPage - 1) * $pageSize));

        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */


        // Get Orders List Using Skip count pagination.
        $orders = $query->get()->toArray();


        // Filter orders list to remove null or empty string.
        $orders = array_filter($orders, function ($value) {
            return $value !== "" && !is_null($value);
        });


        return response()->json([
            'status' => 200,
            "message" => "Orders list for company" . $user->name,
            "data" => $orders
        ]);
    }
    
    
    
     public function generatePdf(Request $request)
    {


        $order = Order::findOrFail($request->orderId);
        if (!$order) {
            return response()->json([
                'status' => 400,
                "message" => "Order Not Found."
            ]);
        }

        $regularPath = "files/orders/reports/orderReport_$order->id.pdf";
        
        

        if (\File::exists(public_path($regularPath))):
            //\File::delete(public_path($regularPath));
        else:
            

            $pdf = PDF::loadView('mypdf', ['order' => $order]);
            $pdf->save(public_path("files/orders/reports/orderReport_$order->id.pdf"));
        endif;

        return response()->json([
            'status' => 200,
            "pdf" => request()->root().'/public/'.$regularPath,
        ]);


    }
    
    
    
}
