<?php

namespace App\Http\Controllers;


use App\Libraries\PushNotification;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Slider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use Auth;


class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public $main;
    public $push;

    public function __construct(Request $request, \App\Libraries\Main $main, PushNotification $push)
    {
//        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
//        app()->setLocale($language);

        $this->main = $main;
        $this->push = $push;

    }


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {

        $products = Product::orderBy('created_at', 'desc')->limit(12)->get();

        $featuredProducts = Product::whereIsFeatured(1)->get();

        $offers = Offer::whereDate('started_at', '>=', Carbon::today()->toDateString())
            ->whereDate('started_at', Carbon::today()->toDateString())
            ->orWhere('started_at', '<', Carbon::today()->toDateString())
            ->whereDate('ended_at', '>=', Carbon::today()->toDateString())
            ->orderBy('created_at', 'desc')->limit(12)->get();

        $sliders  = Slider::all();
        $banners  = Banner::all();

        return view('home.index', compact('products', 'featuredProducts', 'offers', 'sliders', 'banners'));
    }


    public function categories()
    {
        $categories = Category::IsActive()->where('type', "!=", 1)->get()->reverse();
        return view('categories.index')->with(compact('categories'));
    }


    public function categoriesProducts($id)
    {
        $category = Category::findOrFail($id);
        $categoryProducts = Product::orderBy('created_at', 'desc')->whereCategoryId($id)->IsActive()->get();
        return view('categories.products')->with(compact('categoryProducts', 'category'));
    }


    public function productDetails($id)
    {

        $product = Product::with('offer')->findOrFail($id);
        return view('products.details')->with(compact('product'));

    }

    public function productFavorite(Request $request)
    {
        if (!auth()->check())
            return response()->json([
                'status' => false,
                'message' => __('trans.shouldBeLoggedin'),
            ]);


        if ($request->type == 1) {


            $isExsit = Favorite::where(['product_id' => $request->id, 'user_id' => auth()->id()])->first();


            if ($isExsit) {
                return;
            }

            $favorite = new Favorite();
            $favorite->product_id = $request->id;
            $favorite->user_id = auth()->id();
            if ($favorite->save()) {
                return response()->json([
                    'status' => true,
                    'message' => __('trans.productAddToFav'),
                    'data' => $favorite,
                    'type' => $request->type
                ]);
            }
        } else {
            $favorite = Favorite::where(['product_id' => $request->id, 'user_id' => auth()->id()])->first();
            if ($favorite->delete()) {
                return response()->json([
                    'status' => true,
                    'message' => __('trans.productRemoveFromFav'),
                    'type' => $request->type

                ]);
            }
        }

    }


    public function send_message(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            return 0;
        }


        $message = new Messages();
        $message->sender_id = Auth::user()->id;
        $message->reciever_id = 0;
        $message->message = $request->message ? $request->message : "";
        $message->save();


        $file = $request->file('image');
        if ($request->hasFile('image')) {
            $fileName = 'message-photo-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
            $destinationPath = 'uploads';
            $request->file('image')->move($destinationPath, $fileName);
            $message->image = $fileName;
        }
        $message->save();


        return response()->json(
            [
                'message' => $message->message,
                'id' => $message->id,
            ]);
    }


    public function get_messages_unread()
    {
        $message = Messages::where('reciever_id', Auth::user()->id)->where('status', 0)->orderBy('created_at', 'DESC')->first();
        if ($message) {
            $message->status = 1;
            $message->save();
        }
        if ($message) {
            return response()->json(['message' => $message->message, 'id' => $message->id]);
        } else {
            return response()->json(0);
        }
    }


    public function subscription(Request $request)
    {


        if ($request->email != "") {
            $newsletter = new  Newsletter();
            $newsletter->email = $request->email;
            if ($newsletter->save()) {


                $data = array(
                    "user_id" => 1,
                    'title' => "إشتراك جديد",
                    'body' => "لديك اشتراك جديد في النشرة البريدية",
                    'item_id' => $newsletter->id,
                    'type' => "newsletter",
                    "url" => url('/administrator/newsletters'),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                );

                $this->main->insertData(Notification::class, $data);
                $this->push->sendPushNotification([], getAdminDevices(), $data['title'], $data['body'], $data);


                $inputs = $request->all();
                $inputs['message'] = __("trans.subscription_newsletter");
                sendGeneralEmail('emails.subscription', $inputs);
                return response()->json([
                    'status' => 200,
                    'message' => __('trans.success_subscription')
                ]);
            } else {
                return response()->json([
                    'status' => 400,
                    'message' => __('trans.something_error')
                ]);
            }


        } else {
            return response()->json([
                'status' => 402,
                'message' => __('trans.please_enter_email')
            ]);
        }

    }


    public function getTypes(Request $request)
    {


        if ($request->itemId == 1) {
            $items = Companytype::orderBy('created_at', 'desc')->get();
        } elseif ($request->itemId == 2) {
            $items = Service::orderBy('created_at', 'desc')->get();
        } else {
            $items = Agenttype::orderBy('created_at', 'desc')->get();
        }

        return $items;
    }


    public function weather()
    {
        return view('lists.weather');
    }

    public function currencyConverter()
    {
        return view('lists.currency-converter');
    }


    public function postCurrencyConverter(Request $request)
    {
        $amount = $request->amount;
        $from = $request->convert_from;
        $to = $request->convert_to;

        $string = $from . "_" . $to;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://free.currconv.com/api/v7/convert?q=" . $string . "&compact=ultra&apiKey=b0a8dc624b8d81def330",
            CURLOPT_RETURNTRANSFER => 1
        ));

        $response = curl_exec($curl);

        $response = json_decode($response, true);

        $rate = $response[$string];
        $total = $rate * $amount;

        return response()->json([
            'status' => 200,
            'total' => $total
        ]);
    }

    public function prayTimes()
    {
        return view('lists.pray-times');
    }

}
