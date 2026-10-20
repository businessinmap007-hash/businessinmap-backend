<?php

namespace App\Services\OwnerListings;

use App\Models\GuaranteeLevel;
use App\Models\OwnerListing;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Guarantees\GuaranteeBadgeService;
use App\Services\Guarantees\GuaranteePenaltyService;
use App\Services\Guarantees\OperationGuarantorService;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\WalletService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «جدية الإعلان» (owner, 2026-10-28): a person who lists something stands behind it with a STAKE of the size the control panel
 * sets (event `owner_listing_deposit`, role `owner`; switched off = no condition at all):
 *
 *  - an account with a guarantee of at least bronze (`bim.owner_listings.min_guarantee_level`) whose free coverage reaches that
 *    size has the coverage FROZEN for the whole life of the listing; or
 *  - anyone else leaves a DEPOSIT of that size, held in the wallet (balance → locked).
 *
 * The stake goes back when the listing is sold or finished, deleted, or ends. When a moderator removes the listing for a
 * violation it is CONFISCATED instead: a deposit goes to the platform treasury, a guarantee is charged the same sum.
 */
final class OwnerListingSeriousness
{
    public const STATE_HELD = 'held';
    public const STATE_RELEASED = 'released';
    public const STATE_FORFEITED = 'forfeited';

    public const KIND_WALLET = 'wallet';
    public const KIND_GUARANTEE = 'guarantee';

    /** Operation type the guarantee services know a frozen listing stake by. */
    public const OPERATION = 'owner_listing';

    /**
     * The rules a moderator removes a listing for — the same list the new-listing page warns about. key → [Arabic, English].
     */
    public const VIOLATIONS = [
        'fraud' => ['احتيال أو معلومات مضللة', 'Fraud or misleading information'],
        'inappropriate' => ['محتوى غير لائق', 'Inappropriate content'],
        'prohibited' => ['سلعة أو خدمة ممنوعة', 'A prohibited item or service'],
        'wrong_info' => ['بيانات أو صور غير صحيحة', 'Wrong details or photos'],
        'not_owner' => ['إعلان عن سلعة لا يملكها المعلِن', 'An item the advertiser does not own'],
        'off_platform' => ['نقل التعامل خارج المنصة أو تبادل أرقام الهاتف قبل الاتفاق', 'Moving the deal off the platform or swapping phone numbers before agreeing'],
        'spam' => ['إعلان مكرر أو مزعج', 'A repeated or spammy listing'],
    ];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly PlatformEventFeeService $fees,
        private readonly GuaranteeBadgeService $badges,
        private readonly OperationGuarantorService $guarantors,
        private readonly GuaranteePenaltyService $penalties,
        private readonly PlatformTreasuryService $treasury
    ) {}

    /** @return list<array{key:string,name:string}> */
    public static function violations(bool $english): array
    {
        $out = [];

        foreach (self::VIOLATIONS as $key => $names) {
            $out[] = ['key' => $key, 'name' => $names[$english ? 1 : 0]];
        }

        return $out;
    }

    private function targetOf(User $user): string
    {
        return (string) $user->type === User::TYPE_BUSINESS ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT;
    }

    /**
     * What this person must do to publish.
     *
     * @return array{required:bool,guarantee_ok:bool,needs_deposit:bool,deposit_amount:float,balance:float,min_level:array{code:string,name:string},violations:list<array{key:string,name:string}>}
     */
    public function requirement(User $user, ?bool $english = null): array
    {
        $english ??= app()->getLocale() === 'en';
        $amount = $this->fees->quote(PlatformEventFeeService::OWNER_LISTING_DEPOSIT, ['owner' => (int) $user->id], 0)['total'];
        $required = $amount > 0;

        $code = (string) config('bim.owner_listings.min_guarantee_level', 'bronze');
        $target = $this->targetOf($user);
        $min = GuaranteeLevel::query()->where('code', $code)->where('target_type', $target)->first();
        $badge = $this->badges->forUsers([(int) $user->id])[(int) $user->id] ?? null;
        $levelOk = $badge !== null && $min !== null && (int) $badge['priority'] >= (int) $min->priority;
        // the level is not enough on its own: its free coverage must reach the stake, since that is what gets frozen
        $guaranteeOk = $levelOk && $this->guarantors->usableCoverage($user, $target) >= $amount;

        return [
            'required' => $required,
            'guarantee_ok' => $guaranteeOk,
            'needs_deposit' => $required && ! $guaranteeOk,
            'deposit_amount' => $amount,
            'balance' => (float) $this->wallets->getOrCreateWallet((int) $user->id)->balance,
            'min_level' => ['code' => $code, 'name' => (string) (($english ? $min?->name_en : $min?->name_ar) ?: $code)],
            'violations' => self::violations($english),
        ];
    }

    /**
     * Before publishing: fail unless the condition is met — by the guarantee (frozen later), or by agreeing to the deposit with
     * enough balance. Returns what will stand behind the listing: kind + amount (kind null = nothing required).
     *
     * @return array{kind:?string,amount:float}
     */
    public function assertCanPublish(User $user, bool $holdDeposit): array
    {
        $req = $this->requirement($user);

        if (! $req['required']) {
            return ['kind' => null, 'amount' => 0.0];
        }

        if ($req['guarantee_ok']) {
            return ['kind' => self::KIND_GUARANTEE, 'amount' => (float) $req['deposit_amount']];
        }

        $amount = $this->money($req['deposit_amount']);

        if (! $holdDeposit) {
            throw ValidationException::withMessages(['seriousness' => __('لنشر إعلانك: ضمان :level فأعلى، أو عربون جدية :amount جنيه يُحجز من محفظتك ويُردّ عند البيع أو الإنهاء.', ['level' => $req['min_level']['name'], 'amount' => $amount])]);
        }

        if ($req['balance'] < $req['deposit_amount']) {
            throw ValidationException::withMessages(['balance' => __('رصيدك لا يكفي لعربون الجدية (:amount جنيه). اشحن محفظتك أولًا.', ['amount' => $amount])]);
        }

        return ['kind' => self::KIND_WALLET, 'amount' => (float) $req['deposit_amount']];
    }

    /**
     * Put the stake behind the listing, once: freeze the guarantee coverage, or hold the deposit in the wallet.
     *
     * @param  array{kind:?string,amount:float}  $stake
     */
    public function hold(OwnerListing $listing, array $stake): void
    {
        if ($stake['kind'] === null || $stake['amount'] <= 0 || $listing->deposit_state === self::STATE_HELD) {
            return;
        }

        if ($stake['kind'] === self::KIND_GUARANTEE) {
            $this->guarantors->freezeSelf(self::OPERATION, (int) $listing->id, $listing->owner, $stake['amount'], $this->targetOf($listing->owner));
        } else {
            $this->wallets->hold(
                (int) $listing->user_id,
                $stake['amount'],
                'owner_listing_deposit',
                (string) $listing->id,
                'عربون جدية إعلان من المالك',
                'ol-deposit-hold:' . $listing->id . ':' . Str::uuid(),
                ['context' => 'owner_listing_deposit', 'listing_id' => (int) $listing->id]
            );
        }

        $listing->forceFill(['deposit_amount' => $stake['amount'], 'deposit_kind' => $stake['kind'], 'deposit_state' => self::STATE_HELD])->save();
    }

    /** Give the stake back (deposit unlocked, or the guarantee coverage unfrozen), once; safe when nothing is held. */
    public function release(OwnerListing $listing): void
    {
        if ($listing->deposit_state !== self::STATE_HELD || (float) $listing->deposit_amount <= 0) {
            return;
        }

        if ($listing->deposit_kind === self::KIND_GUARANTEE) {
            $this->guarantors->releaseOperation(self::OPERATION, (int) $listing->id);
        } else {
            $this->wallets->release(
                (int) $listing->user_id,
                (float) $listing->deposit_amount,
                'owner_listing_deposit',
                (string) $listing->id,
                'رد عربون جدية إعلان من المالك',
                'ol-deposit-release:' . $listing->id . ':' . Str::uuid(),
                ['context' => 'owner_listing_deposit', 'listing_id' => (int) $listing->id]
            );
        }

        $listing->forceFill(['deposit_state' => self::STATE_RELEASED])->save();
    }

    /**
     * A moderator removed the listing for a violation: the stake is taken, not returned. A deposit leaves the locked balance for
     * the platform treasury; a guarantee is unfrozen and then charged the same sum (taken from the wallet first, then from
     * the guarantee's locked money, as every guarantee penalty is). Returns the sum confiscated (0 when there was none, or when
     * the owner's funds could not cover it — that failure is logged, never thrown at the moderator).
     */
    public function forfeit(OwnerListing $listing, string $reason): float
    {
        if ($listing->deposit_state !== self::STATE_HELD || (float) $listing->deposit_amount <= 0) {
            return 0.0;
        }

        $amount = round((float) $listing->deposit_amount, 2);
        $key = 'ol-forfeit:' . $listing->id;

        try {
            if ($listing->deposit_kind === self::KIND_GUARANTEE) {
                $this->guarantors->releaseOperation(self::OPERATION, (int) $listing->id);
                $this->penalties->applyPenalty(
                    $listing->owner,
                    $amount,
                    $this->targetOf($listing->owner),
                    OwnerListing::class,
                    (int) $listing->id,
                    'Owner listing removed by a moderator: ' . $reason,
                    ['idempotency_key' => $key]
                );
            } else {
                $this->wallets->captureLocked(
                    (int) $listing->user_id,
                    $amount,
                    'owner_listing_deposit',
                    (string) $listing->id,
                    'مصادرة عربون جدية إعلان أزاله المشرف',
                    $key,
                    ['context' => 'owner_listing_deposit', 'listing_id' => (int) $listing->id, 'violation' => $reason]
                );
            }

            $this->treasury->credit($amount, PlatformTreasuryService::PURPOSE_FINE, (string) $listing->id, $key . ':treasury', ['context' => 'owner_listing_forfeit', 'violation' => $reason]);
        } catch (\Throwable $e) {
            report($e);

            // whatever could not be taken goes back, so nothing stays frozen for a listing that no longer exists
            $this->release($listing);

            return 0.0;
        }

        $listing->forceFill(['deposit_state' => self::STATE_FORFEITED])->save();

        return $amount;
    }

    private function money(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
