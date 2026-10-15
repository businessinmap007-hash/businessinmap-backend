<?php

namespace App\Services\Deposits;

use App\Models\Wallet;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Services\Guarantees\GuaranteeOperationCoverageService;
use App\Services\Guarantees\OperationGuarantorService;

/**
 * The money rules of a deposit, written once for every operation that can carry one.
 *
 * A business sets its terms once («شروط الحجز»: a deposit frozen in both wallets, a frozen guarantee, or a deposit paid
 * outside the platform). Whatever the operation — a booking, a clinic appointment, a lab order — the same questions are
 * asked of it, and this class answers them:
 *
 *   assess      what each side owes, how much of it the guarantee (the customer's own plus accepted friends) covers,
 *               what is left for the wallet, whether each side is ready, and — when a side is short — how it may
 *               close the gap (a friend's guarantee, a better guarantee, a top-up)
 *   holdPlan    from a readiness result: the wallet remainder to freeze and the policy adjusted for it
 *   freezeOwnGuarantee    lock the customer's OWN coverage actually used (friends froze theirs on accepting)
 *   freezeBusinessGuarantee   lock the business's coverage used for its counter share
 *   releaseGuarantees    return every frozen coverage of the operation
 *
 * The business's counter share follows the same rule as the customer's, guarantee first (المالك، 2026-10-20): a
 * business that asks for a deposit stands behind it with its OWN guarantee coverage, frozen per operation, and only the
 * remainder is held from its wallet. The wallet money itself is never moved here: the wallet hold stays with the operation's own deposit service, which knows the
 * operation's target row. Lifted from ServiceExecutionEngine without a change in what it answers.
 */
class DepositCore
{
    public function __construct(
        protected OperationGuarantorService $guarantors,
        protected GuaranteeOperationCoverageService $coverage,
    ) {
    }

    /**
     * A user's wallet as the readiness check reads it.
     *
     * @return array{exists:bool,active:bool,balance:float,locked_balance:float,status:?string}
     */
    public function walletSnapshot(int $userId): array
    {
        $none = ['exists' => false, 'active' => false, 'balance' => 0.0, 'locked_balance' => 0.0, 'status' => null];

        if ($userId <= 0) {
            return $none;
        }

        $wallet = Wallet::query()->where('user_id', $userId)->first();

        if (! $wallet) {
            return $none;
        }

        return [
            'exists' => true,
            'active' => (string) ($wallet->status ?? '') === 'active',
            'balance' => round((float) ($wallet->balance ?? 0), 2),
            'locked_balance' => round((float) ($wallet->locked_balance ?? 0), 2),
            'status' => (string) ($wallet->status ?? ''),
        ];
    }

    /**
     * One side's guarantee, asked whether it can stand for $amount of this operation.
     *
     * @return array<string,mixed>
     */
    public function coverageDecision(DepositOperation $op, ?User $user, float $amount, string $targetType, string $side): array
    {
        $amount = round(max($amount, 0), 2);

        if (! $user || $amount <= 0) {
            return [
                'covered' => false,
                'reason' => $amount <= 0 ? 'not_required' : 'missing_user',
                'operation_type' => $op->type,
                'operation_id' => $op->id,
                'amount' => $amount,
                'target_type' => $targetType,
                'user_id' => $user ? (int) $user->id : null,
                'side' => $side,
            ];
        }

        return array_merge(
            $this->coverage->check(
                user: $user,
                amount: $amount,
                operationType: $op->type,
                operationId: $op->id,
                targetType: $targetType,
                context: ['source' => $op->source, 'side' => $side]
                    + $op->context
                    + ['coverage_purpose' => $side === 'client' ? 'wallet_hold' : 'business_counter_hold']
            ),
            ['side' => $side]
        );
    }

    /**
     * What this operation's deposit asks of each side, and whether each side can stand it.
     *
     * @param  array<string,mixed>  $policy              the deposit calculator's result for this operation
     * @param  bool                 $alreadyFrozen       the operation's deposit is frozen already — nothing more is asked
     * @param  float                $clientFeeRequired   fees the operation also charges the client (kept in the same total)
     * @param  float                $businessFeeRequired fees the operation also charges the business
     * @return array<string,mixed>
     */
    public function assess(DepositOperation $op, array $policy, bool $alreadyFrozen, float $clientFeeRequired = 0.0, float $businessFeeRequired = 0.0): array
    {
        $clientDepositRequired = 0.0;
        $businessDepositRequired = 0.0;

        if (($policy['required'] ?? false) && ! $alreadyFrozen) {
            $clientDepositRequired = round((float) ($policy['wallet_hold_amount'] ?? $policy['hold'] ?? 0), 2);
            // a business that requires the guarantee: no wallet hold, the customer's guarantee itself is frozen
            if ($clientDepositRequired <= 0) {
                $clientDepositRequired = round((float) ($policy['guarantee_hold_amount'] ?? 0), 2);
            }
            // the RAW counter share: the calculator zeroes `business_counter_hold_amount` when the business's guarantee
            // would cover it all, but the guarantee is now frozen for it (below) rather than left idle
            $businessDepositRequired = round((float) ($policy['business_counter_hold_raw_amount'] ?? $policy['business_counter_hold_amount'] ?? 0), 2);
        }

        // Guarantee-as-deposit: the client's own coverage plus any accepted friend co-guarantors' frozen coverage. It is
        // applied against the required deposit; only the remainder needs a wallet hold — so guarantee + wallet combine
        // (e.g. 1500 guarantee + 200 balance covers a 1700 deposit).
        $combinedGuaranteeCoverage = $clientDepositRequired > 0 && $op->client
            ? $this->guarantors->combinedCoverage($op->type, $op->id, $op->client)
            : 0.0;
        $guaranteeApplied = round(min($clientDepositRequired, $combinedGuaranteeCoverage), 2);

        $clientWallet = $this->walletSnapshot((int) ($op->client?->id ?? 0));
        $businessWallet = $this->walletSnapshot((int) ($op->business?->id ?? 0));

        $clientCoverage = $this->coverageDecision($op, $op->client, $clientDepositRequired, GuaranteeLevel::TARGET_CLIENT, 'client');
        $businessCoverage = $this->coverageDecision($op, $op->business, $businessDepositRequired, GuaranteeLevel::TARGET_BUSINESS, 'business');

        // Fully covered by guarantee when the applied coverage meets the whole deposit (used for the skip-hold flag +
        // payload); otherwise the wallet holds only the remainder.
        $clientCovered = $clientDepositRequired > 0 && $guaranteeApplied >= $clientDepositRequired;

        // The business's share: its own usable coverage first, the wallet for what is left.
        $businessCoverageAvailable = $businessDepositRequired > 0 && $op->business
            ? $this->guarantors->usableCoverage($op->business, GuaranteeLevel::TARGET_BUSINESS)
            : 0.0;
        $businessGuaranteeApplied = round(min($businessDepositRequired, $businessCoverageAvailable), 2);
        $businessCovered = $businessDepositRequired > 0 && $businessGuaranteeApplied >= $businessDepositRequired;

        $clientWalletRequired = round(max($clientDepositRequired - $guaranteeApplied, 0), 2);
        $businessWalletRequired = round(max($businessDepositRequired - $businessGuaranteeApplied, 0), 2);

        $clientRequiredTotal = round($clientWalletRequired + $clientFeeRequired, 2);
        $businessRequiredTotal = round($businessWalletRequired + $businessFeeRequired, 2);

        $clientReady = $clientWallet['active'] && $clientWallet['balance'] >= $clientRequiredTotal;
        $businessReady = $businessWallet['active'] && $businessWallet['balance'] >= $businessRequiredTotal;

        $messages = [];
        // The same messages, filed under the party each one is about, so a caller can show a party only its own.
        $bySide = ['client' => [], 'business' => []];

        if ((bool) data_get($policy, 'client_guarantee_covered', false) && ! $clientCovered && $clientDepositRequired > 0) {
            $messages[] = $bySide['client'][] = __('ضمان طالب الحجز غير كافٍ أو غير صالح لتغطية Wallet Hold لهذا الحجز.');
        }

        if ((bool) data_get($policy, 'business_guarantee_covered', false) && ! $businessCovered && $businessDepositRequired > 0) {
            $messages[] = $bySide['business'][] = __('ضمان مقدم الخدمة غير كافٍ أو غير صالح لتغطية Counter Hold لهذا الحجز.');
        }

        if (! $clientReady && $clientRequiredTotal > 0) {
            $messages[] = $bySide['client'][] = __('رصيد طالب الحجز غير كافٍ لتجميد الديبوزت أو خصم رسوم الخدمة.');
        }

        if (! $businessReady && $businessRequiredTotal > 0) {
            $messages[] = $bySide['business'][] = __('رصيد مقدم الخدمة غير كافٍ لتجميد الديبوزت أو خصم رسوم الخدمة.');
        }

        // When the client can't proceed, offer concrete ways to close the gap: bring in a friend guarantor, upgrade the
        // guarantee, or top up balance.
        $clientOptions = [];
        if (! ($clientReady || $clientRequiredTotal <= 0)) {
            $balanceShortfall = round(max($clientRequiredTotal - round((float) ($clientWallet['balance'] ?? 0), 2), 0), 2);
            $guaranteeGap = round(max($clientDepositRequired - $combinedGuaranteeCoverage, 0), 2);

            $clientOptions = [
                'request_friend_guarantee' => $guaranteeGap > 0 ? ['needed_coverage' => $guaranteeGap] : null,
                'upgrade_guarantee' => $guaranteeGap > 0,
                'add_balance' => $balanceShortfall > 0 ? ['amount' => $balanceShortfall] : null,
            ];
        }

        return [
            'client_deposit_required' => $clientDepositRequired,
            'business_deposit_required' => $businessDepositRequired,
            'combined_guarantee_coverage' => round($combinedGuaranteeCoverage, 2),
            'guarantee_applied' => $guaranteeApplied,
            'business_guarantee_applied' => $businessGuaranteeApplied,
            'client_covered_by_guarantee' => $clientCovered,
            'business_covered_by_guarantee' => $businessCovered,
            'client_wallet_required' => $clientWalletRequired,
            'business_wallet_required' => $businessWalletRequired,
            'client_required_total' => $clientRequiredTotal,
            'business_required_total' => $businessRequiredTotal,
            'client_ready' => $clientReady || $clientRequiredTotal <= 0,
            'business_ready' => $businessReady || $businessRequiredTotal <= 0,
            'client_wallet' => $clientWallet,
            'business_wallet' => $businessWallet,
            'client_coverage' => $clientCoverage,
            'business_coverage' => $businessCoverage,
            'client_options' => $clientOptions,
            'messages' => $messages,
            'messages_by_side' => $bySide,
        ];
    }

    /**
     * Turn a readiness result into what must actually be frozen in the wallet: the remainder after the guarantee, and
     * the policy adjusted for it (a business whose guarantee covers its counter hold freezes none).
     *
     * @param  array<string,mixed>  $policy     the deposit policy of the operation
     * @param  array<string,mixed>  $readiness  the readiness preview — its `deposit` block
     * @return array{policy:array<string,mixed>,wallet_hold:float,wallet_hold_required:bool,business_covered:bool}
     */
    public function holdPlan(array $policy, array $readiness): array
    {
        $businessCovered = (bool) data_get($readiness, 'deposit.business_guarantee_covered', false);

        // Guarantee (self + accepted friend co-guarantors) offsets the deposit; the wallet holds only the remainder
        // computed by the readiness preview. Full guarantee coverage → 0 → no hold.
        $walletHold = round((float) data_get($readiness, 'deposit.client_wallet_required', 0), 2);
        $required = (bool) ($policy['wallet_hold_required'] ?? false) && $walletHold > 0;

        // the business freezes from its own guarantee first; only the remainder is held from its wallet
        $businessWallet = round((float) data_get($readiness, 'deposit.business_wallet_required', 0), 2);
        $policy['business_counter_hold_amount'] = $businessWallet;
        $policy['business_counter_hold_required'] = $businessWallet > 0;

        if ($required) {
            // Freeze only the wallet remainder (guarantee covers the rest).
            $policy['wallet_hold_amount'] = $walletHold;
            $policy['hold'] = $walletHold;
        }

        return [
            'policy' => $policy,
            'wallet_hold' => $walletHold,
            'wallet_hold_required' => $required,
            'business_wallet_hold' => $businessWallet,
            'business_covered' => $businessCovered,
        ];
    }

    /**
     * Lock the customer's OWN guarantee coverage actually used (guarantee applied minus what accepted friends already
     * froze — they froze theirs on accepting). Coverage is frozen for the duration of the operation, never charged;
     * releaseGuarantees() returns it. Returns the amount frozen.
     *
     * @param  array<string,mixed>  $readiness  the readiness preview
     */
    public function freezeOwnGuarantee(DepositOperation $op, array $readiness): float
    {
        if (! $op->client) {
            return 0.0;
        }

        $guaranteeApplied = round((float) data_get($readiness, 'deposit.guarantee_applied', 0), 2);
        $friendsFrozen = $this->guarantors->friendsCoverage($op->type, $op->id, $op->client);
        $selfFreeze = round(max($guaranteeApplied - $friendsFrozen, 0), 2);

        if ($selfFreeze > 0) {
            $this->guarantors->freezeSelf($op->type, $op->id, $op->client, $selfFreeze);
        }

        return $selfFreeze;
    }

    /**
     * Lock the business's OWN guarantee coverage that stands for its counter share of the deposit (recorded against the
     * operation like the customer's, so releaseGuarantees() returns both). Returns the amount frozen.
     *
     * @param  array<string,mixed>  $readiness  the readiness preview
     */
    public function freezeBusinessGuarantee(DepositOperation $op, array $readiness): float
    {
        if (! $op->business) {
            return 0.0;
        }

        $applied = round((float) data_get($readiness, 'deposit.business_guarantee_applied', 0), 2);

        if ($applied > 0) {
            $this->guarantors->freezeSelf($op->type, $op->id, $op->business, $applied, GuaranteeLevel::TARGET_BUSINESS);
        }

        return $applied;
    }

    /** Return every frozen coverage of the operation (the customer's own and the friends'). */
    public function releaseGuarantees(DepositOperation $op): void
    {
        $this->guarantors->releaseOperation($op->type, $op->id);
    }
}
