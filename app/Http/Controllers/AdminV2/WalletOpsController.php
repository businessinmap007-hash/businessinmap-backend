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

        $users = $this->searchUsers($q, $q === '' ? 20 : 50);

        $user = null;

        if ($userId > 0) {
            $user = User::query()->select('id', 'name', 'email', 'phone', 'type')->find($userId);
        } elseif ($q !== '') {
            $user = $users->first();
        }

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

    public function searchUsersJson(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if ($q === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $rows = $this->searchUsers($q, 12)
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?: 'بدون اسم'),
                'email' => (string) ($user->email ?: ''),
                'phone' => (string) ($user->phone ?: ''),
                'type' => (string) ($user->type ?: ''),
                'label' => '#' . $user->id . ' — ' . ($user->name ?: 'بدون اسم') . ' — ' . ($user->type ?: 'user') . ' — ' . ($user->phone ?: $user->email),
                'url' => route('admin.wallet-ops.recharge.form', ['user_id' => (int) $user->id, 'q' => $q]),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
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
            'request_token' => ['required', 'string', 'max:64'],
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
                // Stable per-form nonce so a double-submit is deduped by the
                // ledger's idempotency (wallet_id + key), instead of the old
                // uniqid()+timestamp which was unique every call.
                'idempotency_key' => 'admin_recharge:' . $user->id . ':' . $data['request_token'],
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
            $level = $this->validLevelForUser((int) $data['guarantee_level_id'], $user);

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

    public function activateGuarantee(Request $request, GuaranteeAutoUpgradeService $guaranteeAutoUpgradeService)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'guarantee_level_id' => ['required', 'integer', 'exists:guarantee_levels,id'],
        ]);

        $user = User::query()
            ->select('id', 'name', 'type')
            ->findOrFail((int) $data['user_id']);

        $level = $this->validLevelForUser((int) $data['guarantee_level_id'], $user);

        $result = $guaranteeAutoUpgradeService->upgradeToLevel(
            user: $user,
            level: $level,
            referenceType: 'admin_wallet_balance',
            referenceId: (int) $user->id,
            meta: [
                'source' => 'wallet_ops_activate_guarantee',
                'admin_id' => auth()->id(),
                'guarantee_action' => 'manual_from_existing_balance',
            ]
        );

        $message = 'تم تنفيذ تفعيل الضمان من الرصيد الحالي.';

        if (($result['changed'] ?? false) && ! empty($result['level'])) {
            $message .= ' المستوى: ' . $result['level']->display_name . '.';
        } elseif (! ($result['changed'] ?? false)) {
            $message .= ' النتيجة: ' . ($result['reason'] ?? 'no_change') . '.';
        }

        return redirect()
            ->route('admin.wallet-ops.recharge.form', ['user_id' => $user->id])
            ->with('success', $message);
    }

    private function searchUsers(string $q, int $limit)
    {
        return User::query()
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
            ->limit($limit)
            ->get();
    }

    private function validLevelForUser(int $levelId, User $user): GuaranteeLevel
    {
        $level = GuaranteeLevel::query()
            ->where('id', $levelId)
            ->where('target_type', $user->isBusiness() ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT)
            ->where('is_active', 1)
            ->first();

        if (! $level) {
            abort(422, 'مستوى الضمان المختار غير مناسب لنوع المستخدم أو غير مفعل.');
        }

        return $level;
    }
}
