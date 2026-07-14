<?php

namespace App\Services\Payments;

use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Settlement of wallet top-up intents — the single place that turns a paid
 * top-up into a wallet credit. Shared by the gateway callback (real-time) and
 * the reconciliation poller (safety net for missed callbacks), so both settle
 * identically and idempotently.
 */
class WalletTopupService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /**
     * Credit the points wallet for a paid top-up and mark it paid. Idempotent:
     * keyed on `wallet_topup:{id}` and guarded by a row-locked status check, so a
     * replayed callback or an overlapping poll can never double-credit.
     */
    public function markPaid(WalletTopup $topup, ?string $gatewayRef, ?string $method): WalletTopup
    {
        return DB::transaction(function () use ($topup, $gatewayRef, $method) {
            /** @var WalletTopup $locked */
            $locked = WalletTopup::where('id', $topup->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return $locked; // already settled
            }

            $this->wallet->deposit(
                (int) $locked->user_id,
                $locked->amount,
                'شحن رصيد عبر ' . $locked->gateway,
                'wallet_topup',
                (string) $locked->id,
                'wallet_topup:' . $locked->id,
                [
                    'gateway' => $locked->gateway,
                    'method' => $method,
                    'gateway_ref' => $gatewayRef,
                ],
            );

            $locked->update([
                'status' => WalletTopup::STATUS_PAID,
                'gateway_ref' => $gatewayRef,
                'method' => $method,
                'paid_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /** Record a terminal failure on a still-pending top-up (no wallet change). */
    public function markFailed(WalletTopup $topup, ?string $gatewayRef = null, ?string $method = null): void
    {
        if ($topup->isPending()) {
            $topup->update([
                'status' => WalletTopup::STATUS_FAILED,
                'gateway_ref' => $gatewayRef,
                'method' => $method,
            ]);
        }
    }
}
