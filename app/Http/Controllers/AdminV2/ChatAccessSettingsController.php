<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ChatAccessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The one dial this feature exposes: how many distinct admins must each
 * approve a chat before it unlocks without the parties' own consent. See
 * App\Services\Chat\ThreadAccessGateService, which is the only reader of
 * this value.
 */
class ChatAccessSettingsController extends Controller
{
    /**
     * GET admin/chat-access-settings
     *
     * View data key is `accessSetting`, not `setting` — a legacy global
     * composer (ViewServiceProvider) injects its own `$setting` (a bare
     * `App\Models\Setting`) into EVERY view including this one, silently
     * clobbering a same-named variable and rendering the form blank.
     */
    public function edit(): View
    {
        $accessSetting = ChatAccessSetting::query()->firstOrCreate([], ['admin_quorum' => 3]);

        return view('admin-v2.chat-access-settings.edit', ['accessSetting' => $accessSetting]);
    }

    /** PUT admin/chat-access-settings */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admin_quorum' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $accessSetting = ChatAccessSetting::query()->firstOrCreate([], ['admin_quorum' => 3]);
        $accessSetting->update($data);

        return redirect()
            ->route('admin.chat-access-settings.edit')
            ->with('success', __('تم حفظ إعدادات الاطلاع على المحادثات.'));
    }
}
