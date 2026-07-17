<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Models\Country;
use Illuminate\Http\Request;

/**
 * ⚠️ Legacy web address form — routed but non-functional, and left in place
 * rather than deleted (v1 is kept deliberately; parts are still being ported).
 *
 * index() renders `addresses.index`, a view that does not exist, so it throws.
 * store() used to look up `locations` for name_en = 'Egypt' in a table where all
 * 71 rows have an empty name_en, so it always answered "الدولة الافتراضية غير
 * موجودة". Between them, no address was ever created — the table has zero rows.
 *
 * The live address book is Api\V2\AddressController. The id-space bug is fixed
 * here anyway so this cannot quietly write governorate ids from a different
 * table into the same columns if anyone revives it.
 */
class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $countries = Country::query()->orderBy('name_ar')->get();

        return view('addresses.index', compact('countries'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAddressRequest $request)
    {
        // Derived from the chosen governorate rather than hardcoded to Egypt:
        // `countries` now holds all 249 ISO countries.
        $governorate = \App\Models\Governorate::query()->find($request->governorate_id);
        $country = $governorate?->country;

        if (!$country) {
            return returnedResponse(422, 'المحافظة المختارة غير مرتبطة بدولة');
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
