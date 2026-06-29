<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\UserPushToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user is required.',
            ], 401);
        }

        $data = $request->validate([
            'platform' => ['required', Rule::in(UserPushToken::platforms())],
            'provider' => ['nullable', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'token' => ['required', 'string', 'max:1000'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'meta' => ['nullable', 'array'],
        ]);

        $data['provider'] = $data['provider'] ?? 'fcm';

        $row = UserPushToken::query()->updateOrCreate(
            [
                'user_id' => (int) $user->id,
                'token' => (string) $data['token'],
            ],
            [
                'platform' => (string) $data['platform'],
                'provider' => (string) $data['provider'],
                'device_id' => $data['device_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'is_active' => 1,
                'last_seen_at' => now(),
                'meta' => $data['meta'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push token registered successfully.',
            'data' => [
                'push_token' => $row,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user is required.',
            ], 401);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:1000'],
        ]);

        UserPushToken::query()
            ->where('user_id', (int) $user->id)
            ->where('token', (string) $data['token'])
            ->update([
                'is_active' => 0,
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Push token disabled successfully.',
        ]);
    }
}
