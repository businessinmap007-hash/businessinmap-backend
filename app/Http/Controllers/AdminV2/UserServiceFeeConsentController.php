<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserServiceFeeConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserServiceFeeConsentController extends Controller
{
    public function edit(User $user): View
    {
        $user->load([
            'serviceFeeConsent',
            'wallet:id,user_id,balance,locked_balance,status,total_in,total_out',
            'category:id,name_ar,name_en',
            'categoryChild:id,name_ar,name_en',
        ]);

        $consent = $user->serviceFeeConsent ?: new UserServiceFeeConsent([
            'user_id' => $user->id,
            'fee_auto_charge_enabled' => false,
            'rating_enabled' => false,
            'stats_enabled' => false,
        ]);

        return view('admin-v2.user-service-fee-consents.edit', [
            'user' => $user,
            'consent' => $consent,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'fee_auto_charge_enabled' => ['nullable'],
            'rating_enabled' => ['nullable'],
            'stats_enabled' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'fee_auto_charge_enabled' => 'تفعيل خصم رسوم التنفيذ تلقائيًا',
            'rating_enabled' => 'تفعيل التقييم',
            'stats_enabled' => 'تفعيل الإحصائيات',
            'notes' => 'الملاحظات',
        ]);

        $feeEnabled = $request->boolean('fee_auto_charge_enabled');
        $ratingEnabled = $request->boolean('rating_enabled');
        $statsEnabled = $request->boolean('stats_enabled');

        $existing = UserServiceFeeConsent::query()
            ->where('user_id', $user->id)
            ->first();

        $payload = [
            'user_id' => $user->id,
            'fee_auto_charge_enabled' => $feeEnabled ? 1 : 0,
            'rating_enabled' => $ratingEnabled ? 1 : 0,
            'stats_enabled' => $statsEnabled ? 1 : 0,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];

        if ($existing) {
            $wasFeeEnabled = (bool) $existing->fee_auto_charge_enabled;

            if (! $wasFeeEnabled && $feeEnabled) {
                $payload['enabled_at'] = now();
                $payload['disabled_at'] = null;
            }

            if ($wasFeeEnabled && ! $feeEnabled) {
                $payload['disabled_at'] = now();
            }

            if ($wasFeeEnabled && $feeEnabled && empty($existing->enabled_at)) {
                $payload['enabled_at'] = now();
            }

            $existing->update($payload);
        } else {
            $payload['enabled_at'] = $feeEnabled ? now() : null;
            $payload['disabled_at'] = $feeEnabled ? null : now();

            UserServiceFeeConsent::query()->create($payload);
        }

        return redirect()
            ->route('admin.user-service-fee-consents.edit', $user)
            ->with('success', 'تم تحديث موافقات رسوم الخدمة بنجاح.');
    }

    public function enableCharging(User $user): RedirectResponse
    {
        $consent = UserServiceFeeConsent::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'fee_auto_charge_enabled' => false,
                'rating_enabled' => false,
                'stats_enabled' => false,
            ]
        );

        $consent->enableCharging('تم التفعيل من لوحة الإدارة.');

        return back()->with('success', 'تم تفعيل الخصم التلقائي لرسوم الخدمة.');
    }

    public function disableCharging(User $user): RedirectResponse
    {
        $consent = UserServiceFeeConsent::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'fee_auto_charge_enabled' => false,
                'rating_enabled' => false,
                'stats_enabled' => false,
            ]
        );

        $consent->disableCharging('تم التعطيل من لوحة الإدارة.');

        return back()->with('success', 'تم تعطيل الخصم التلقائي لرسوم الخدمة.');
    }
}