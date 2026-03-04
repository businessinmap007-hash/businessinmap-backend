<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Wallet;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Facades\DB;

final class PaymentConfirmService
{
    public function __construct(private WalletLedgerService $ledger) {}

    public function confirmAndDeposit(int $paymentId, int $noteId = 0): void
    {
        $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

        // ✅ لازم يكون paid
        abort_unless($payment->paid_at, 422, 'Payment not paid yet');

        $wallet = Wallet::query()
            ->where('user_id', $payment->user_id)
            ->lockForUpdate()
            ->firstOrFail();

        $idem = 'pay:' . $payment->id;

        DB::transaction(function () use ($payment, $wallet, $idem, $noteId) {
            $this->ledger->deposit(
                walletId: (int)$wallet->id,
                userId: (int)$payment->user_id,
                amount: (float)$payment->price,
                op: [
                    'idempotency_key' => $idem,
                    'reference_type'  => 'payments',
                    'reference_id'    => (string)$payment->id,
                    'meta'            => [
                        'payment_type'   => (string)$payment->payment_type,
                        'payment_no'     => (string)$payment->payment_no,
                        'operation_type' => (string)$payment->operation_type,
                        'operation_id'   => (string)$payment->operation_id,
                    ],
                    'note_id'         => (int)$noteId, // ✅ ثابت/مختار من templates
                ]
            );
        });
    }
}