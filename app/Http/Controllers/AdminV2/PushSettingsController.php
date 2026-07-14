<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Services\Notifications\FirebasePushService;
use App\Services\Notifications\PushSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 screen for pasting the Firebase (FCM) service-account JSON so push
 * notifications can go live without a code or .env change. Mirrors the Fawry
 * PaymentSettingsController: values persist (encrypted) via PushSettingsService
 * and FirebasePushService picks them up on the next send.
 */
class PushSettingsController extends Controller
{
    public function __construct(private readonly PushSettingsService $settings)
    {
    }

    /** GET admin/push-settings */
    public function edit(): View
    {
        return view('admin-v2.push-settings.edit', [
            'firebase' => $this->settings->firebaseFormState(),
        ]);
    }

    /** PUT admin/push-settings */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'service_account_json' => ['nullable', 'string', 'max:20000'],
        ]);

        $error = $this->settings->saveFirebase($request->input('service_account_json'));

        if ($error !== null) {
            return redirect()
                ->route('admin.push-settings.edit')
                ->withErrors(['service_account_json' => $error]);
        }

        return redirect()
            ->route('admin.push-settings.edit')
            ->with('success', 'تم حفظ بيانات اعتماد Firebase بنجاح.');
    }

    /** POST admin/push-settings/test — verify the stored credentials against Google. */
    public function test(FirebasePushService $push): RedirectResponse
    {
        $result = $push->verifyCredentials();

        if ($result['ok']) {
            return redirect()
                ->route('admin.push-settings.edit')
                ->with('success', 'نجح الاتصال بـ Firebase — المشروع: ' . ($result['project_id'] ?? '—'));
        }

        $reason = match ($result['reason']) {
            'no_project_id' => 'لا توجد بيانات اعتماد مضبوطة (project_id مفقود).',
            'token_exchange_failed' => 'فشل تبادل الرمز مع Google — تحقّق من صحّة ملف الحساب الخدمي.',
            default => 'تعذّر التحقّق من بيانات الاعتماد.',
        };

        return redirect()
            ->route('admin.push-settings.edit')
            ->withErrors(['service_account_json' => $reason]);
    }
}
