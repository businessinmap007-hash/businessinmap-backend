<?php

namespace App\Services\Deposits;

use App\Models\User;

/**
 * The operation a deposit or a guarantee stands behind — a booking, a clinic appointment, a lab order, a procedure…
 * It names only what the core needs: what kind it is and which one, the two parties, and the extra facts a coverage
 * decision records (service, business, client) so the trail reads the same whichever operation asked.
 */
final class DepositOperation
{
    /**
     * @param string               $type     the operation's kind, shared by the guarantee services (OperationGuarantor::OP_*)
     * @param array<string,mixed>  $context  extra facts kept on every coverage decision, in this order
     * @param string               $source   who asked — kept on the coverage decision for the audit trail
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly ?User $client,
        public readonly ?User $business,
        public readonly array $context = [],
        public readonly string $source = 'DepositCore.assess',
    ) {
    }
}
