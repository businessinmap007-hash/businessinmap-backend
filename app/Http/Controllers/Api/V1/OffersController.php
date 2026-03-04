<?php

namespace App\Http\Controllers\Api\V1;

use App\Image;
use App\Company;
use App\Offer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Images;

class OffersController extends Controller
{
    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/companies/offers/';
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveOffer(Request $request)
    {

        $company = Company::whereId($request->companyId)->first();

        if ($countOffers = $this->getOffersAvilable($request->companyId) >= $company->membership->offer_time) {
            return response()->json([
                'status' => false,
                'message' => 'عفواً, لا يمكنك إضافة عرض الان من فضلك انتظر حتى انتهاء مدة العرض ثم اعد المحاولة'
            ]);
        }

        if (!$company)
            return response()->json(['status' => false, 'message' => 'Company Not Found in System']);


        $offer = new Offer;
        $offer->name = $request->name;
        $offer->description = $request->description;

        if ($request->hasFile('image')):
            $offer->image = $request->root() . '/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
        endif;
        if ($company->offers()->save($offer)) {

            $idsArr = $request->imagesIds;

            if (isset($idsArr)) {
                $data = explode(',', $idsArr);
                foreach ($data as $key => $value) {
                    $image = Image::whereId($value)->first();
                    $image->company_id = $company->id;
                    $offer->images()->save($image);
                }
            }

            return response()->json([
                'status' => true,
            ]);

        }


    }


    private function getOffersAvilable($company)
    {

        $query = Company::whereId($company)->first();

        $query->offers->map(function ($q) {
            if ($q) {
                $original = new Carbon($q->created_at);
                $q->duration = $this->offerDuration($q->id);
                $date = $original->addDays($q->duration);
                $q->expiration = is_object($q) ? $date->toDateTimeString() : '';
                $changeDate = strtotime($q->expiration) - strtotime(Carbon::now());
                $q->diffDate = $changeDate;
                return $q;
            }
        });


        $offers = $query->offers->filter(function ($q) {
            return $q->diffDate > 0;
        })->values();

        return $offers->count();


    }


    public function dailyOvers(Request $request)
    {

        $pageSize = $request->pageSize;
        $skipCount = $request->skipCount;

        $currentPage = $request->get('page', 1); // Default to 1


        if ($request->companyId) :
            $query = Offer::where('company_id', $request->companyId)->with('company', 'images')->orderBy('created_at', 'desc')->get();

        else:
            $query = Offer::with('company', 'images')->orderBy('created_at', 'desc')->get();
        endif;


        $query->map(function ($q) use ($request) {
            if ($q) {
                $original = new Carbon($q->created_at);
                $q->duration = $this->offerDuration($q->id);
                $date = $original->addDays($q->duration);
                $q->expiration = is_object($q) ? $date->toDateTimeString() : '';
                $changeDate = strtotime($q->expiration) - strtotime(Carbon::now());
                $q->diffDate = $changeDate;
                return $q;
            }
        });

        $offers = $query->filter(function ($q) use ($request) {
            if ($request->companyId) :
                return $q->diffDate > 0 || $q->diffDate < 0;
            else:
                return $q->diffDate > 0;
            endif;
        })->slice($skipCount + (($currentPage - 1) * $pageSize))
            ->take($pageSize)
            ->values();

        return response()->json([
            'status' => true,
            'data' => $offers
        ]);
    }


    /**
     * @param $offer
     * @return string
     */
    public function offerDuration($offer)
    {
        $offer = Offer::whereId($offer)->first();
        return ($of = $offer->company) ?
            ($of->membership) ?
                $of->membership->offer_time : '' : '';
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @ Delete
     */

    public function delete(Request $request)
    {
        $model = Offer::whereId($request->offerId)->first();
        if (!$model) {
            return response()->json([
                'status' => false,
                'message' => 'هذا العرض غير موجود'
            ]);
        }

        $original = new Carbon($model->created_at);
        $model->duration = $this->offerDuration($model->id);
        $date = $original->addDays($model->duration);
        $model->expiration = is_object($model) ? $date->toDateTimeString() : '';
        $changeDate = strtotime($model->expiration) - strtotime(Carbon::now());
        $model->diffDate = $changeDate;
        if ($model->diffDate > 0) {
            return response()->json([
                'status' => false,
                'message' => 'عفوا, لايمكنك حذف العرض الان, حاول بعد انتهاء مده العرض'
            ]);
        }



        if ($model->delete()) {

            return response()->json([
                'status' => true,
                'message' => 'لقد تم حذف العرض بنجاح'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'لقد حدث خطأ, من فضلك حاول مرة آخرى'
            ]);
        }
    }


    public function update(Request $request)
    {
        $model = Offer::whereId($request->offerId)->first();

        $model->name = $request->name;

        $model->description = $request->description;
        if ($request->hasFile('image')):
            $model->image = $request->root() . '/' . $this->public_path . UploadImage::uploadImage($request, 'image', $this->public_path);
        endif;
        if ($model->save()) {

            $idsArr = $request->imagesIds;

            if (isset($idsArr)) {
                $data = explode(',', $idsArr);
                foreach ($data as $key => $value) {
                    $image = Image::whereId($value)->first();
                    $model->images()->save($image);
                }
            }
            return response()->json([
                'status' => true,
                'data' => $model
            ]);
        }
    }






}
