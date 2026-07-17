<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // `locations` was never the new system despite the comment that used
            // to sit here: it holds 71 country rows with empty names and no
            // governorates or cities at all. The live tables are governorates /
            // cities — see Api\V2\AddressController and §14 of the engineering
            // reference. Corrected here so this request cannot write ids from a
            // different id space into the same columns if it is ever revived.
            'governorate_id' => 'required|exists:governorates,id',
            'city_id'        => 'required|exists:cities,id',
            'address_line'   => 'required|string|min:5|max:255',

            // GPS (اختياري)
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',

            // النظام القديم (اختياري)
            'zip_code' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'governorate_id.required' => 'برجاء اختيار المحافظة',
            'city_id.required'        => 'برجاء اختيار المدينة',
            'address_line.required'   => 'برجاء إدخال العنوان التفصيلي',
            'address_line.min'        => 'العنوان قصير جدًا',
        ];
    }
}
