<?php

namespace App\Http\Requests\Sponsors;

use Illuminate\Foundation\Http\FormRequest;

class PostSponsorFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required_if:type,paid',
            'description' =>'required_if:type,paid',
            'type' => 'in:paid,free',
            'expire_at' => 'min:6',
            'price' => 'required_if:type,paid',
            'image' => 'required',
        ];
    }
}
