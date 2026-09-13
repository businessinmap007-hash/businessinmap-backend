<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class UserRegistrationService
{
    public static function create(array $data): User
    {
        return User::create([
            'name'       => $data['name'] ?? null,
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'password'   => $data['password'] ?? null,
            'type'       => $data['type'] ?? 'client',
            'is_active'  => 1,
            'is_suspend' => 0,
            'api_token'  => Str::random(120),
        ]);
    }
}
