<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class SetPinRequest extends FormRequest
{
    public function authorize()
    {
        return true; // لأننا نعتمد auth:sanctum
    }

    public function rules()
    {
        return [
            'pin' => 'required|digits:6'
        ];
    }

    public function messages()
    {
        return [
            'pin.required' => 'PIN is required',
            'pin.digits'   => 'PIN must be 6 digits'
        ];
    }
}
