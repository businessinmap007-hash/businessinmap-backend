<?php

namespace App\Http\Requests\Escrow;

use Illuminate\Foundation\Http\FormRequest;

class CreateEscrowRequest extends FormRequest
{
    public function authorize()
    {
        return true; // التحقق يتم داخل EscrowService
    }

    public function rules()
    {
        return [
            'business_id'     => 'required|exists:users,id',
            'client_amount'   => 'required|numeric|min:0',
            'business_amount' => 'required|numeric|min:0',
            'order_id'        => 'nullable|integer|exists:orders,id',
        ];
    }

    public function messages()
    {
        return [
            'business_id.required' => 'Business ID is required',
            'business_id.exists'   => 'Business user not found',

            'client_amount.required' => 'Client amount is required',
            'client_amount.numeric'  => 'Client amount must be numeric',

            'business_amount.required' => 'Business amount is required',
            'business_amount.numeric'  => 'Business amount must be numeric',
        ];
    }
}
