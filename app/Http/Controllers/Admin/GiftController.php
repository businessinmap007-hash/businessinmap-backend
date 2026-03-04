<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GiftController extends Controller
{
    public function store(Request $request, User $user)
    {

        $inputs = $request->except('_token');

        if (!$user->gifts)
            $user->gifts()->create($inputs);
        else
            $user->fill($inputs)->gifts()->update($inputs);

        return returnedResponse(200, 'لقد إضافة نسب الخصم والهدايا للعميل بنجاح.', null, route('business.show', $user->id));

    }
}
