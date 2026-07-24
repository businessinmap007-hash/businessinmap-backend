<?php

namespace App\Services\Payments;

use App\Models\MerchantPayment;
use Illuminate\Support\Facades\DB;

/**
 * Settlement of customer→merchant payment intents. Unlike WalletTopupService it
 * credits NO platform wallet — the money already settles into the merchant's own
 * gateway account (or the platform's for later payout). This service only flips
 * the intent's status, idempotently and under a row lock, so a replayed callback
 * can't double-settle.
 */
class MerchantPaymentService
{
    /** Mark a payment paid. Idempotent + row-locked. */
    public function markPaid(MerchantPayment $payment, ?string $gatewayRef, ?string $method): MerchantPayment
    {
        return DB::transaction(function () use ($payment, $gatewayRef, $method) {
            /** @var MerchantPayment $locked */
            $locked = MerchantPayment::where('id', $payment->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return $locked; // already settled
            }

            $locked->update([
                'status' => MerchantPayment::STATUS_PAID,
                'gateway_ref' => $gatewayRef,
                'method' => $method,
                'paid_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /** Record a terminal failure on a still-pending payment. */
    public function markFailed(MerchantPayment $payment, ?string $gatewayRef = null, ?string $method = null): void
    {
        if ($payment->isPending()) {
            $payment->update([
                'status' => MerchantPayment::STATUS_FAILED,
                'gateway_ref' => $gatewayRef,
                'method' => $method,
            ]);
        }
    }
}
