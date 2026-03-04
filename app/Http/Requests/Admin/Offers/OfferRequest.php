<?php

namespace App\Http\Requests\Admin\Offers;

use Illuminate\Foundation\Http\FormRequest;

class OfferRequest extends FormRequest
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
        switch ($this->method()) {
            case 'GET':
            case 'DELETE':
                {
                    return [];
                }
            case 'POST':
                {
                    return [
                        'name:ar' => 'required|max:255',
                        'name:en' => 'required|max:255',

                    ];
                }
            case 'PUT':
            case 'PATCH':
                {
                    return [
                        'name:ar' => 'required|max:255',
                        'name:en' => 'required|max:255',
                    ];
                }
            default:
                break;
        }
    }
}
