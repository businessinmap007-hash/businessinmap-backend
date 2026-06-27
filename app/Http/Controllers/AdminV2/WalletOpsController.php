<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Guarantees\GuaranteeAutoUpgradeService;
use App\Services\WalletLedgerService;
use Illuminate\Http\Request;

final class WalletOpsController extends Controller
{
    public function rechargeForm(Request $request)
    {
        $userId = (int) $request->get('user_id', 0);

        $user = $userId
            ? User::query()->select('id', 'name', 'type')->find($userId)
            : null;

        return view('admin-v2.wallet-ops.recharge', compact('user'));
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
            'guarantee_level_id' => ['nullable', 'integer', 'exists:guarantee_levels,id'],
        ]);

        $user = User::query()
            ->select('id', 'name', 'type')
            ->findOrFail((int) $data['user_id']);

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'locked_balance' => 0, 'status' => 'active']
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
                ],
                'note' => (string) ($data['note'] ?? ''),
            ]
        );

        if (! empty($data['note_id'])) {
            $tx->note_id = (int) $data['note_id'];
            $tx->save();
        }

        $upgradeResult = null;

        if (! empty($data['guarantee_level_id'])) {
            $level = GuaranteeLevel::query()
                ->where('id', (int) $data['guarantee_level_id'])
                ->where('target_type', $user->isBusiness() ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT)
                ->where('is_active', 1)
                ->first();

            if ($level) {
                $upgradeResult = $guaranteeAutoUpgradeService->upgradeToLevel(
                    user: $user,
                    level: $level,
                    referenceType: 'wallet_transaction',
                    referenceId: (int) $tx->id,
                    meta: [
                        'source' => 'wallet_ops_recharge',
                        'wallet_transaction_id' => (int) $tx->id,
                        'admin_id' => auth()->id(),
                    ]
                );
            }
        } else {
            $upgradeResult = $guaranteeAutoUpgradeService->autoUpgrade(
                user: $user,
                referenceType: 'wallet_transaction',
                referenceId: (int) $tx->id,
                meta: [
                    'source' => 'wallet_ops_recharge',
                    'wallet_transaction_id' => (int) $tx->id,
                    'admin_id' => auth()->id(),
                ]
            );
        }

        $message = 'تم شحن المحفظة بنجاح.';

        if (($upgradeResult['changed'] ?? false) && ! empty($upgradeResult['level'])) {
            $message .= ' وتم تحديث مستوى الضمان تلقائيًا إلى: ' . $upgradeResult['level']->display_name . '.';
        }

        return redirect()
            ->route('admin.wallet-transactions.show', ['walletTransaction' => $tx->id])
            ->with('success', $message);
    }
}
