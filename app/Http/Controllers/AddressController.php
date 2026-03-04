<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Models\Location;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // الدول من locations (النظام الجديد)
        $countries = Location::where('type', 'country')->get();

        return view('addresses.index', compact('countries'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAddressRequest $request)
    {
        // الدولة الافتراضية (مصر)
        $country = Location::where('type', 'country')
                           ->where('name_en', 'Egypt')
                           ->first();

        if (!$country) {
            return returnedResponse(500, 'الدولة الافتراضية غير موجودة');
        }

        // إلغاء أي عنوان أساسي سابق
        auth()->user()->addresses()->update(['is_primary' => 0]);

        $address = auth()->user()->addresses()->create([

            /* ======================
             |  النظام الجديد
             |======================*/
            'country_id'     => $country->id,
            'governorate_id' => $request->governorate_id,
            'city_id'        => $request->city_id,
            'address_line'   => $request->address_line,
            'lat'            => $request->lat,
            'lng'            => $request->lng,

            /* ======================
             |  التوافق مع النظام القديم (مؤقت)
             |======================*/
            'location_id'    => $request->city_id,
            'latitude'       => $request->lat,
            'longitude'      => $request->lng,
            'zip_code'       => $request->zip_code,

            'is_primary'     => 1,
        ]);

        if ($address) {
            return returnedResponse(
                200,
                'لقد تم إضافة العنوان بنجاح',
                null,
                route('addresses.index')
            );
        }
    }

    /**
     * Update primary address
     */
    public function updatePrimaryAddress(Request $request)
    {
        if ($request->addressId) {
            auth()->user()->addresses()->update(['is_primary' => 0]);
        }

        $address = auth()->user()->addresses()->findOrFail($request->addressId);
        $address->is_primary = 1;

        if ($address->save()) {
            return returnedResponse(
                200,
                __('trans.address_changed_to_primary'),
                $address,
                null
            );
        }
    }
}
