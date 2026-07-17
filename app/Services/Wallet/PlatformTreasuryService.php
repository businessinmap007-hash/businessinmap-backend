<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Facades\Log;

/**
 * The platform's own account — the credit side of the ledger.
 *
 * Until this existed, a service fee was debited from the payer's wallet and
 * credited to nobody: the money left the ledger entirely, so the sum of all
 * wallets shrank with every fee and no account held what the platform earned.
 * This is that missing counterparty, so a fee becomes a real movement between
 * two wallets instead of money evaporating.
 *
 * Every credit carries a PURPOSE, because the balance here is not one kind of
 * money:
 *   - fee    → revenue. The platform's.
 *   - fine   → revenue, but contestable and possibly reversed.
 *   - escheat→ NOT revenue. A vanished user's balance the platform is holding.
 *              It may have to go back (a claim, a mistaken deletion, unclaimed
 *              -property law), so it must never be counted as earnings.
 * Reading the raw balance tells you nothing; read balanceByPurpose().
 */
class PlatformTreasuryService
{
    public const PURPOSE_FEE = 'platform_fee';
    public const PURPOSE_FINE = 'platform_fine';
    public const PURPOSE_ESCHEAT = 'platform_escheat';

    public const PURPOSES = [
        self::PURPOSE_FEE,
        self::PURPOSE_FINE,
        self::PURPOSE_ESCHEAT,
    ];

    public function __construct(private readonly WalletService $wallets) {}

    /** The configured treasury account id, or null when not configured yet. */
    public function accountId(): ?int
    {
        $id = (int) config('bim.platform_wallet_user_id');

        return $id > 0 ? $id : null;
    }

    public function isConfigured(): bool
    {
        return $this->accountId() !== null;
    }

    /** Is this user the treasury? Used to keep it out of normal user flows. */
    public function isTreasury(?int $userId): bool
    {
        return $userId !== null && $this->accountId() === (int) $userId;
    }

    /**
     * Credit the treasury.
     *
     * Deliberately best-effort: the caller has usually already taken the money
     * off the payer, and a treasury problem must never leave the payer charged
     * with the operation rolled back. A failure here is loud in the log and
     * reconcilable from the payer's own transaction, which is never lost.
     *
     * Idempotent — pass the same key as the debit side (suffixed), so a retry
     * cannot credit twice.
     */
    public function credit(
        float $amount,
        string $purpose,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): ?WalletTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0 || ! in_array($purpose, self::PURPOSES, true)) {
            return null;
        }

        $accountId = $this->accountId();

        if ($accountId === null) {
            Log::warning('Platform treasury is not configured; a movement was not credited.', [
                'purpose' => $purpose,
                'amount' => $amount,
                'reference_id' => $referenceId,
            ]);

            return null;
        }

        try {
            return $this->wallets->deposit(
                userId: $accountId,
                amount: $amount,
                note: $this->noteFor($purpose),
                referenceType: $purpose,
                referenceId: $referenceId,
                idempotencyKey: $idempotencyKey,
                meta: $meta + ['purpose' => $purpose]
            );
        } catch (\Throwable $e) {
            Log::error('Crediting the platform treasury failed; the payer was still charged.', [
                'purpose' => $purpose,
                'amount' => $amount,
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The treasury split by what the money actually is. Escheat is a liability
     * sitting in the same balance as revenue — this is the only honest way to
     * read it.
     *
     * @return array{fee: float, fine: float, escheat: float, total: float}
     */
    public function balanceByPurpose(): array
    {
        $accountId = $this->accountId();

        $out = ['fee' => 0.0, 'fine' => 0.0, 'escheat' => 0.0, 'total' => 0.0];

        if ($accountId === null) {
            return $out;
        }

        $sums = WalletTransaction::query()
            ->where('user_id', $accountId)
            ->where('direction', 'in')
            ->whereIn('reference_type', self::PURPOSES)
            ->selectRaw('reference_type, SUM(amount) as total')
            ->groupBy('reference_type')
            ->pluck('total', 'reference_type');

        $out['fee'] = round((float) ($sums[self::PURPOSE_FEE] ?? 0), 2);
        $out['fine'] = round((float) ($sums[self::PURPOSE_FINE] ?? 0), 2);
        $out['escheat'] = round((float) ($sums[self::PURPOSE_ESCHEAT] ?? 0), 2);
        $out['total'] = round((float) Wallet::query()->where('user_id', $accountId)->value('balance'), 2);

        return $out;
    }

    /** The treasury user, if configured and present. */
    public function account(): ?User
    {
        $id = $this->accountId();

        return $id === null ? null : User::query()->find($id);
    }

    private function noteFor(string $purpose): string
    {
        return match ($purpose) {
            self::PURPOSE_FEE => 'رسوم خدمة',
            self::PURPOSE_FINE => 'غرامة',
            self::PURPOSE_ESCHEAT => 'رصيد حساب محذوف',
            default => 'حركة منصة',
        };
    }
}
