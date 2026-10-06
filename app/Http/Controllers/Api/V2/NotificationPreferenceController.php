<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Support\NotificationCategories;
use Illuminate\Http\Request;

/**
 * «اعدادات الاشعارات» — one switch per category: on = active, off = silent (still in the inbox, no push, no sound).
 */
class NotificationPreferenceController extends Controller
{
    /** GET /api/v2/me/notification-preferences */
    public function show(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['categories' => $this->payload((int) $request->user()->id)]]);
    }

    /** PUT /api/v2/me/notification-preferences — `{categories: {orders: false, messages: true}}`; a category left out is untouched. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*' => ['boolean'],
        ]);
        $userId = (int) $request->user()->id;

        foreach ($data['categories'] as $key => $enabled) {
            if (! array_key_exists($key, NotificationCategories::ALL)) {
                continue;
            }
            NotificationPreference::query()->updateOrCreate(['user_id' => $userId, 'category' => $key], ['enabled' => (bool) $enabled]);
        }

        return response()->json(['success' => true, 'data' => ['categories' => $this->payload($userId)]]);
    }

    /** @return list<array{key:string,label:string,enabled:bool}> */
    private function payload(int $userId): array
    {
        $state = NotificationCategories::stateFor($userId);
        $english = app()->getLocale() === 'en';

        return collect(NotificationCategories::ALL)->map(fn ($c, $key) => [
            'key' => $key,
            'label' => $english ? $c[1] : $c[0],
            'enabled' => $state[$key],
        ])->values()->all();
    }
}
