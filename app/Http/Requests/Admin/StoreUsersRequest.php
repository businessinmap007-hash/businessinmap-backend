<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUsersRequest extends FormRequest
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
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'first_name.required' => __('trans.first_name_required'),
            'last_name.required' => __('trans.last_name_required'),
            'email.required' => __('trans.email_required'),
            'email.email' => __('trans.email_format_incorrect'),
            'email.unique' => __('trans.email_unique'),
            'phone.required' => __('trans.phone_required'),
            'phone.unique' => __('trans.phone_unique'),
            'password.required' => __('trans.password_required')
        ];
        return parent::messages();
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), 422));
    }

}
