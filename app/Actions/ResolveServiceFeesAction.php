<?php

namespace App\Actions;

use App\Models\Booking;
use App\Services\WalletFeeService;
use Illuminate\Support\Collection;

/**
 * BIM-3.5 — the one place to ask "what does this operation cost in fees, and
 * why".
 *
 * Resolving a fee walks three layers (base fee → dynamic rules → promotion) and
 * callers should not have to know that. This action is the stable seam over
 * them: `execute` answers what would be charged without charging anything, and
 * `explain` answers why, which is what an admin screen or a support question
 * actually needs.
 *
 * Charging is deliberately not here — that stays WalletFeeService::applyBookingFees,
 * so resolving a fee can never accidentally move money.
 */
final class ResolveServiceFeesAction
{
    public function __construct(private readonly WalletFeeService $fees) {}

    /**
     * The fee lines that would be charged for this booking, one per payer.
     * Read-only: no wallet is touched.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Booking $booking, string $feeCode = WalletFeeService::DEFAULT_FEE_CODE): Collection
    {
        return $this->fees->resolveBookingFees($booking, $feeCode);
    }

    /**
     * The same resolution, reshaped into the story of how each side's fee was
     * reached: what the static base said, which rules moved it and by how much,
     * what a promotion then discounted, and the final charge.
     *
     * @return array<string, array<string, mixed>> keyed by payer
     */
    public function explain(Booking $booking, string $feeCode = WalletFeeService::DEFAULT_FEE_CODE): array
    {
        $out = [];

        foreach ($this->execute($booking, $feeCode) as $line) {
            $payer = (string) ($line['payer'] ?? 'unknown');
            $final = round((float) ($line['amount'] ?? 0), 2);

            // Each layer only stamps its "before" key when it changed the line,
            // so the earliest present key is the true starting fee.
            $base = (float) ($line['amount_before_rules']
                ?? $line['amount_before_promotion']
                ?? $final);

            $out[$payer] = [
                'payer' => $payer,
                'fee_code' => $line['fee_code'] ?? $feeCode,
                'currency' => $line['currency'] ?? 'EGP',
                'base_fee' => round($base, 2),
                'final_fee' => $final,
                'total_change' => round($final - $base, 2),
                'rules_applied' => $line['fee_rules'] ?? [],
                'promotion' => $line['promotion'] ?? null,
                'promotion_discount' => isset($line['promotion_discount_amount'])
                    ? round((float) $line['promotion_discount_amount'], 2)
                    : 0.0,
                'context' => $line['fee_context'] ?? null,
                'source' => $line['source'] ?? null,
            ];
        }

        return $out;
    }
}
