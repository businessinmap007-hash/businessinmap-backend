<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DeviceTokenController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'device_token' => ['required', 'string', 'max:1000'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'device_id' => ['nullable', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'meta' => ['nullable', 'array'],
        ]);

        $row = UserDeviceToken::query()->updateOrCreate(
            ['device_token' => $data['device_token']],
            [
                'user_id' => (int) $request->user()->id,
                'platform' => $data['platform'],
                'device_id' => $data['device_id'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
                'meta' => $data['meta'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'data' => ['device' => $row]]);
    }

    public function unregister(Request $request)
    {
        $data = $request->validate([
            'device_token' => ['required_without:device_id', 'nullable', 'string', 'max:1000'],
            'device_id' => ['required_without:device_token', 'nullable', 'string', 'max:191'],
        ]);

        UserDeviceToken::query()
            ->where('user_id', (int) $request->user()->id)
            ->when(! empty($data['device_token']), fn ($q) => $q->where('device_token', $data['device_token']))
            ->when(! empty($data['device_id']), fn ($q) => $q->where('device_id', $data['device_id']))
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }
}
