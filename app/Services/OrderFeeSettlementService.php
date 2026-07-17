<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\PlatformTreasuryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collects BIM's platform service fee on a menu order from the BUSINESS wallet.
 *
 * A menu order is paid cash on arrival: the customer hands the restaurant the
 * food total + service fee + tax. BIM's cut is the `service_fee` that was folded
 * into that bill — so at settlement we claw it back from the business wallet
 * (the business physically holds the cash). Mirrors the booking platform-fee
 * pattern in WalletFeeService, scoped to one order.
 *
 * Gated on the business's fee-auto-charge consent: `order->service_fee` is only
 * > 0 when the business had opted in at checkout (MenuBillingService), so a
 * non-consenting business is never charged. Idempotent per order.
 */
class OrderFeeSettlementService
{
    public const REFERENCE_TYPE = 'order';

    public function __construct(private readonly PlatformTreasuryService $treasury) {}

    /**
     * Settle the order's platform service fee against the business wallet.
     * Returns the fee transaction, or null when there is nothing to collect.
     *
     * Throws a ValidationException (→ blocks the caller's transition) when the
     * business wallet cannot cover the fee — the business must top up first.
     */
    public function settleForOrder(Order $order): ?WalletTransaction
    {
        $fee = round((float) $order->service_fee, 2);
        $businessId = (int) $order->business_id;

        if ($fee <= 0 || $businessId <= 0) {
            return null; // no fee (no consent / fee-free business) — nothing to do
        }

        $idempotencyKey = 'order_fee:' . (int) $order->id;

        return DB::transaction(function () use ($order, $fee, $businessId, $idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing; // already settled
            }

            $wallet = Wallet::query()->where('user_id', $businessId)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $businessId,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'total_in' => 0,
                    'total_out' => 0,
                    'status' => 'active',
                ]);
                $wallet = Wallet::query()->where('id', $wallet->id)->lockForUpdate()->first();
            }

            $balanceBefore = round((float) $wallet->balance, 2);

            if ($balanceBefore < $fee) {
                // Per product decision: block progression until the business tops up.
                throw ValidationException::withMessages([
                    'wallet' => 'رصيد محفظة النشاط لا يكفي لعمولة الخدمة (' . number_format($fee, 2)
                        . '). يرجى شحن المحفظة لقبول الطلب.',
                ]);
            }

            $balanceAfter = round($balanceBefore - $fee, 2);
            $lockedBefore = round((float) $wallet->locked_balance, 2);

            $wallet->balance = $balanceAfter;
            $wallet->total_out = round((float) $wallet->total_out + $fee, 2);
            $wallet->last_activity_at = now();
            $wallet->save();

            $transaction = WalletTransaction::create([
                'wallet_id' => (int) $wallet->id,
                'user_id' => $businessId,
                'status' => WalletTransaction::STATUS_COMPLETED,
                'direction' => WalletTransaction::DIRECTION_OUT,
                'type' => WalletTransaction::TYPE_PLATFORM_FEE,
                'amount' => $fee,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'locked_before' => $lockedBefore,
                'locked_after' => $lockedBefore,
                'reference_type' => self::REFERENCE_TYPE,
                'reference_id' => (string) $order->id,
                'idempotency_key' => $idempotencyKey,
                'note' => 'عمولة خدمة المنيو للطلب #' . $order->id,
                'meta' => [
                    'order_id' => (int) $order->id,
                    'business_id' => $businessId,
                    'client_id' => (int) $order->user_id,
                    'fee_code' => 'menu_service',
                    'payer' => 'business',
                    'source' => 'order_fee_settlement',
                ],
            ]);

            // The credit half: the fee is now held by the platform rather than
            // debited into nowhere.
            $this->treasury->credit(
                amount: $fee,
                purpose: PlatformTreasuryService::PURPOSE_FEE,
                referenceId: (string) $order->id,
                idempotencyKey: $idempotencyKey.':treasury',
                meta: ['order_id' => (int) $order->id, 'fee_code' => 'menu_service', 'payer' => 'business']
            );

            return $transaction;
        });
    }
}
