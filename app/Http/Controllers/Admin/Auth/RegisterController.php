<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Services\LocationResolverService;

class RegistrationController extends Controller
{
    public function signup(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        $data = $request->validate([
            'name'      => 'required|string|max:191',
            'phone'     => 'required|string|unique:users,phone',
            'email'     => 'nullable|email|unique:users,email',
            'password'  => 'required|min:6',

            // Control flag
            'use_gps'   => 'nullable|boolean',

            // GPS
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            // Manual selection
            'city_id'   => 'nullable|exists:locations,id',

            // Account type
            'type' => 'nullable|in:client,business',
        ]);

        $useGps = $data['use_gps'] ?? true;

        /*
        |--------------------------------------------------------------------------
        | Create user
        |--------------------------------------------------------------------------
        */
        $user = new User();
        $user->name      = $data['name'];
        $user->phone     = $data['phone'];
        $user->email     = $data['email'] ?? null;
        $user->password  = Hash::make($data['password']);
        $user->type      = $data['type'] ?? 'client';
        $user->api_token = Str::random(80);

        /*
        |--------------------------------------------------------------------------
        | GPS or Manual location
        |--------------------------------------------------------------------------
        */
        if ($useGps && !empty($data['latitude']) && !empty($data['longitude'])) {

            // Save coordinates
            $user->latitude  = $data['latitude'];
            $user->longitude = $data['longitude'];

            // Auto detect city
            $hit = app(LocationResolverService::class)
                ->nearestCity(
                    (float) $data['latitude'],
                    (float) $data['longitude']
                );

            if ($hit && $hit['distance_km'] <= 30) {
                $user->city_id = $hit['city_id'];
            }

        } else {
            // Manual selection only
            $user->latitude  = null;
            $user->longitude = null;
            $user->city_id   = $data['city_id'] ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Save user
        |--------------------------------------------------------------------------
        */
        $user->save();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'status'  => 200,
            'message' => 'تم إنشاء الحساب بنجاح',
            'token'   => $user->api_token,
            'user'    => [
                'id'      => $user->id,
                'name'    => $user->name,
                'type'    => $user->type,
                'city_id' => $user->city_id,
                'use_gps' => $useGps,
            ],
        ]);
    }
}
