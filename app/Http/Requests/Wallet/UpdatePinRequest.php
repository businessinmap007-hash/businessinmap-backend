<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePinRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'old_pin' => 'required|digits:6',
            'new_pin' => 'required|digits:6|different:old_pin',
        ];
    }
}
