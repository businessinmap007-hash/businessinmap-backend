<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Http\Request;

final class WalletOpsController extends Controller
{
    public function rechargeForm(Request $request)
    {
        $userId = (int)$request->get('user_id', 0);
        $user = $userId ? User::query()->select('id','name')->find($userId) : null;

        return view('admin-v2.wallet-ops.recharge', compact('user'));
    }

    public function recharge(Request $request, WalletLedgerService $ledger)
    {
        $data = $request->validate([
            'user_id'  => ['required','integer','exists:users,id'],
            'amount'   => ['required','numeric','min:1'],
            'note_id'  => ['nullable','integer','exists:wallet_note_templates,id'],
            'note'     => ['nullable','string','max:500'],
        ]);

        $user = User::query()->select('id','name')->findOrFail((int)$data['user_id']);

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'locked_balance' => 0, 'status' => 'active']
        );

        $tx = $ledger->deposit(
            walletId: (int)$wallet->id,
            userId: (int)$user->id,
            amount: (float)$data['amount'],
            op: [
                'reference_type'  => 'admin_recharge',
                'reference_id'    => (string)$user->id,
                'idempotency_key' => (string)($request->get('idempotency_key') ?? ''),
                'meta' => [
                    'source' => 'admin-v2',
                    'admin_id' => auth()->id(),
                    'note_id' => $data['note_id'] ?? null,
                ],
                // نخزن note النصي لو كتب ملاحظة خاصة
                'note' => (string)($data['note'] ?? ''),
            ]
        );

        // لو تحبي نخزن note_id مباشرة في row (أفضل من meta)
        if (!empty($data['note_id'])) {
            $tx->note_id = (int)$data['note_id'];
            $tx->save();
        }

        return redirect()
            ->route('admin.wallet-transactions.show', ['walletTransaction' => $tx->id])
            ->with('success', 'تم الشحن بنجاح');
    }
}