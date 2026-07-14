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

    /** Build the signed charge for a pending top-up (hosted checkout). */
    public function createCharge(WalletTopup $topup, array $customer = []): ChargeResult;

    /** Verify a server-to-server callback's signature. MUST reject if absent. */
    public function verifyCallbackSignature(array $payload): bool;

    /** Normalize a callback payload to a gateway-agnostic result. */
    public function parseCallback(array $payload): CallbackResult;
}
