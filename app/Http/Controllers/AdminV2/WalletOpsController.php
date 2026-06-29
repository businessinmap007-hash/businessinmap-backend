<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\Guarantees\GuaranteeAutoUpgradeService;
use App\Services\WalletLedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class WalletOpsController extends Controller
{
    public function rechargeForm(Request $request)
    {
        $userId = (int) $request->get('user_id', 0);
        $q = trim((string) $request->get('q', ''));

        $users = User::query()
            ->select('id', 'name', 'email', 'phone', 'type')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    if (is_numeric($q)) {
                        $w->orWhere('id', (int) $q);
                    }

                    $w->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $user = $userId
            ? User::query()->select('id', 'name', 'email', 'phone', 'type')->find($userId)
            : null;

        $wallet = null;
        $activeGuarantee = null;
        $levels = collect();

        if ($user) {
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => (int) $user->id],
                ['balance' => 0, 'locked_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'status' => Wallet::STATUS_ACTIVE]
            );

            $targetType = $user->isBusiness()
                ? GuaranteeLevel::TARGET_BUSINESS
                : GuaranteeLevel::TARGET_CLIENT;

            $levels = GuaranteeLevel::query()
                ->where('target_type', $targetType)
                ->where('is_active', 1)
                ->orderByDesc('priority')
                ->orderBy('required_locked_amount')
                ->get();

            $activeGuarantee = UserGuarantee::query()
                ->with(['purchasedLevel:id,code,name_ar,name_en', 'effectiveLevel:id,code,name_ar,name_en'])
                ->where('user_id', (int) $user->id)
                ->active()
                ->latest('id')
                ->first();
        }

        return view('admin-v2.wallet-ops.recharge', [
            'user' => $user,
            'users' => $users,
            'wallet' => $wallet,
            'levels' => $levels,
            'activeGuarantee' => $activeGuarantee,
            'q' => $q,
        ]);
    }

    public function recharge(
        Request $request,
        WalletLedgerService $ledger,
        GuaranteeAutoUpgradeService $guaranteeAutoUpgradeService
    ) {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note_id' => ['nullable', 'integer', 'exists:wallet_note_templates,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'guarantee_action' => ['nullable', Rule::in(['auto', 'manual', 'none'])],
            'guarantee_level_id' => ['nullable', 'integer', 'exists:guarantee_levels,id'],
        ]);

        $guaranteeAction = $data['guarantee_action'] ?? 'auto';

        if ($guaranteeAction === 'manual' && empty($data['guarantee_level_id'])) {
            return back()
                ->withInput()
                ->withErrors('اختر مستوى الضمان عند اختيار Manual Guarantee Level.');
        }

        $user = User::query()
            ->select('id', 'name', 'type')
            ->findOrFail((int) $data['user_id']);

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'locked_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'status' => Wallet::STATUS_ACTIVE]
        );

        $tx = $ledger->deposit(
            walletId: (int) $wallet->id,
            userId: (int) $user->id,
            amount: (float) $data['amount'],
            op: [
                'reference_type' => 'admin_recharge',
                'reference_id' => (string) $user->id,
                'idempotency_key' => 'admin_recharge:' . $user->id . ':' . now()->format('YmdHis') . ':' . uniqid(),
                'meta' => [
                    'source' => 'admin-v2',
                    'admin_id' => auth()->id(),
                    'note_id' => $data['note_id'] ?? null,
                    'guarantee_action' => $guaranteeAction,
                    'guarantee_level_id' => $data['guarantee_level_id'] ?? null,
                ],
                'note' => (string) ($data['note'] ?? ''),
            ]
        );

        if (! empty($data['note_id'])) {
            $tx->note_id = (int) $data['note_id'];
            $tx->save();
        }

        $upgradeResult = null;

        if ($guaranteeAction === 'manual') {
            $level = GuaranteeLevel::query()
                ->where('id', (int) $data['guarantee_level_id'])
                ->where('target_type', $user->isBusiness() ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT)
                ->where('is_active', 1)
                ->first();

            if (! $level) {
                return back()
                    ->withInput()
                    ->withErrors('مستوى الضمان المختار غير مناسب لنوع المستخدم أو غير مفعل.');
            }

            $upgradeResult = $guaranteeAutoUpgradeService->upgradeToLevel(
                user: $user,
                level: $level,
                referenceType: 'wallet_transaction',
                referenceId: (int) $tx->id,
                meta: [
                    'source' => 'wallet_ops_recharge',
                    'wallet_transaction_id' => (int) $tx->id,
                    'admin_id' => auth()->id(),
                    'guarantee_action' => 'manual',
                ]
            );
        } elseif ($guaranteeAction === 'auto') {
            $upgradeResult = $guaranteeAutoUpgradeService->autoUpgrade(
                user: $user,
                referenceType: 'wallet_transaction',
                referenceId: (int) $tx->id,
                meta: [
                    'source' => 'wallet_ops_recharge',
                    'wallet_transaction_id' => (int) $tx->id,
                    'admin_id' => auth()->id(),
                    'guarantee_action' => 'auto',
                ]
            );
        }

        $message = 'تم شحن المحفظة بنجاح.';

        if ($guaranteeAction === 'none') {
            $message .= ' لم يتم تنفيذ أي إجراء ضمان.';
        }

        if (($upgradeResult['changed'] ?? false) && ! empty($upgradeResult['level'])) {
            $message .= ' وتم تحديث مستوى الضمان إلى: ' . $upgradeResult['level']->display_name . '.';
        } elseif ($upgradeResult && ! ($upgradeResult['changed'] ?? false)) {
            $message .= ' نتيجة الضمان: ' . ($upgradeResult['reason'] ?? 'no_change') . '.';
        }

        return redirect()
            ->route('admin.wallet-ops.recharge.form', ['user_id' => $user->id])
            ->with('success', $message);
    }
}
