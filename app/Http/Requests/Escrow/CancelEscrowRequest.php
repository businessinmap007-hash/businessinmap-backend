<?php

namespace App\Http\Requests\Escrow;

use Illuminate\Foundation\Http\FormRequest;

class CancelEscrowRequest extends FormRequest
{
    public function authorize()
    {
        return true; 
    }

    public function rules()
    {
        return [
            'refund_client'   => 'required|boolean',
            'refund_business' => 'required|boolean',
        ];
    }
}
