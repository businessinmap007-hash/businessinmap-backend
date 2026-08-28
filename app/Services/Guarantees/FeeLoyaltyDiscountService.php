<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLoyaltyGrant;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * A one-time, self-limiting loyalty perk shared by every platform-fee
 * charging path (booking fees, menu-order commission, talent-scouting
 * fees, …): while a user's active guarantee level carries a
 * fee_discount_amount/percent, each fee they pay is shaved down until the
 * running total shaved reaches the level's own price
 * (required_locked_amount) — then it stops, permanently, for that level.
 * Upgrading to a higher level grants a fresh allowance there.
 *
 * Deliberately NOT applied to dispute/arbitration obligations: those are
 * charged against whoever LOST a ruling, not for using a service, and can be
 * collected straight out of a frozen guarantee rather than the free wallet
 * balance — rewarding that with a discount would blur a penalty into a perk.
 */
class FeeLoyaltyDiscountService
{
    public function __construct(private readonly GuaranteeCoverageService $guaranteeCoverage)
    {
    }

    /**
     * @return array{discount: float, level: ?\App\Models\GuaranteeLevel, grant: ?GuaranteeLoyaltyGrant}
     */
    public function apply(User $user, float $amount): array
    {
        $none = ['discount' => 0.0, 'level' => null, 'grant' => null];

        $guarantee = $this->guaranteeCoverage->activeGuarantee($user);

        if (! $guarantee || ! $guarantee->isUsable()) {
            return $none;
        }

        $level = $guarantee->effectiveLevel ?: $guarantee->purchasedLevel;

        if (! $level) {
            return $none;
        }

        $fixedDiscount = $level->fee_discount_amount !== null ? (float) $level->fee_discount_amount : null;
        $percentDiscount = $level->fee_discount_percent !== null ? (float) $level->fee_discount_percent : null;

        if ($fixedDiscount === null && $percentDiscount === null) {
            return $none;
        }

        $cap = round((float) $level->required_locked_amount, 2);

        if ($cap <= 0) {
            return $none;
        }

        $grant = $this->lockOrCreateGrant((int) $user->id, (int) $level->id);

        if ($grant->exhausted_at) {
            return $none;
        }

        $remaining = round($cap - (float) $grant->discount_given, 2);

        if ($remaining <= 0) {
            $grant->exhausted_at = now();
            $grant->save();

            return $none;
        }

        $proposed = $fixedDiscount !== null
            ? $fixedDiscount
            : round($amount * ($percentDiscount / 100), 2);

        // At least 0.01 is always left on the fee itself, so a
        // fully-discounted fee never collapses to zero and trips a caller's
        // "invalid/insufficient fee" guard.
        $discount = min($proposed, $remaining, round($amount - 0.01, 2));

        if ($discount <= 0) {
            return $none;
        }

        $grant->discount_given = round((float) $grant->discount_given + $discount, 2);

        if ($grant->discount_given >= $cap) {
            $grant->exhausted_at = now();
        }

        $grant->save();

        return ['discount' => round($discount, 2), 'level' => $level, 'grant' => $grant];
    }

    /** A short note suffix a caller can append to its own transaction note. */
    public function noteSuffix(array $loyalty): string
    {
        $discount = round((float) ($loyalty['discount'] ?? 0), 2);

        if ($discount <= 0 || ! ($loyalty['level'] ?? null)) {
            return '';
        }

        $levelName = (string) ($loyalty['level']->name_ar ?: $loyalty['level']->name_en ?: $loyalty['level']->code);

        return "خصم ولاء ضمان «{$levelName}»: {$discount}";
    }

    /** A `meta` fragment a caller can merge into its own transaction meta. */
    public function metaFragment(array $loyalty): ?array
    {
        if (($loyalty['discount'] ?? 0) <= 0 || ! ($loyalty['level'] ?? null)) {
            return null;
        }

        return [
            'amount' => round((float) $loyalty['discount'], 2),
            'guarantee_level_id' => (int) $loyalty['level']->id,
            'guarantee_level_code' => $loyalty['level']->code,
            'discount_given_total' => round((float) $loyalty['grant']->discount_given, 2),
            'cap' => round((float) $loyalty['level']->required_locked_amount, 2),
            'exhausted' => $loyalty['grant']->exhausted_at !== null,
        ];
    }

    /**
     * Two operations for the same user's FIRST charge at a level can race:
     * both see no grant row and both try to create one. The unique index
     * (user_id, guarantee_level_id) lets only one INSERT win — the loser
     * would otherwise bubble a raw QueryException up through the fee charge
     * and roll back the caller's whole transaction with it. Catch that one
     * case and just re-read the row the winner created, under lock.
     */
    private function lockOrCreateGrant(int $userId, int $levelId): GuaranteeLoyaltyGrant
    {
        $grant = GuaranteeLoyaltyGrant::query()
            ->where('user_id', $userId)
            ->where('guarantee_level_id', $levelId)
            ->lockForUpdate()
            ->first();

        if ($grant) {
            return $grant;
        }

        try {
            return GuaranteeLoyaltyGrant::create([
                'user_id' => $userId,
                'guarantee_level_id' => $levelId,
                'discount_given' => 0,
            ]);
        } catch (QueryException $e) {
            $grant = GuaranteeLoyaltyGrant::query()
                ->where('user_id', $userId)
                ->where('guarantee_level_id', $levelId)
                ->lockForUpdate()
                ->first();

            if (! $grant) {
                throw $e;
            }

            return $grant;
        }
    }
}
