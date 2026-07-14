<?php

namespace App\Services\Payments;

use App\Models\WalletTopup;
use App\Services\Payments\Dtos\CallbackResult;
use App\Services\Payments\Dtos\ChargeResult;

/**
 * A pluggable money-in gateway. Keeps the wallet top-up flow free of any single
 * provider (Fawry today; Paymob etc. later) — the controller only ever talks to
 * this contract.
 */
interface PaymentGatewayInterface
{
    /** Machine name, e.g. "fawry". */
    public function name(): string;

    /**
     * Build the signed charge for a pending top-up (hosted checkout). `$method`
     * is the app's requested payment method (card / apple_pay / google_pay /
     * fawry_cash / mobile_wallet / valu) or null to let the hosted page offer all.
     */
    public function createCharge(WalletTopup $topup, array $customer = [], ?string $method = null): ChargeResult;

    /** Verify a server-to-server callback's signature. MUST reject if absent. */
    public function verifyCallbackSignature(array $payload): bool;

    /** Normalize a callback payload to a gateway-agnostic result. */
    public function parseCallback(array $payload): CallbackResult;

    /**
     * Poll the gateway for a pending top-up's current status (reconciliation
     * safety net for missed callbacks). Returns null when not configured or the
     * status can't be fetched.
     */
    public function fetchStatus(WalletTopup $topup): ?CallbackResult;
}
