<?php

namespace App\Http\Requests\Albums;

use Illuminate\Foundation\Http\FormRequest;

class AlbumsRequestForm extends FormRequest
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
            'title' => 'required',
            'description' => 'min:3',
            'image' => 'min:3',
            'images.*' => 'min:3'
        ];
    }
}
