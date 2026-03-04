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
            // النظام الجديد
            'governorate_id' => 'required|exists:locations,id',
            'city_id'        => 'required|exists:locations,id',
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
