<?php

namespace App\Services\OwnerListings;

use App\Models\GuaranteeLevel;
use App\Models\OwnerListing;
use App\Models\User;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\Guarantees\GuaranteeBadgeService;
use App\Services\WalletService;
use Illuminate\Validation\ValidationException;

/**
 * «جدية الإعلان» (owner, 2026-10-28): a person who lists something must stand behind it — either an account guarantee of
 * at least bronze (`bim.owner_listings.min_guarantee_level`), or a deposit left for the listing. The deposit's amount is priced
 * in the control panel (event `owner_listing_deposit`, role `owner`; switched off = no condition at all). It is held in
 * the wallet (balance → locked) when the listing is published and handed back when the listing is sold or finished, deleted,
 * or ends. It is never confiscated here: a moderator who removes a listing hands it back too.
 */
final class OwnerListingSeriousness
{
    public const STATE_HELD = 'held';
    public const STATE_RELEASED = 'released';

    public function __construct(
        private readonly WalletService $wallets,
        private readonly PlatformEventFeeService $fees,
        private readonly GuaranteeBadgeService $badges
    ) {}

    /**
     * What this person must do to publish.
     *
     * @return array{required:bool,guarantee_ok:bool,needs_deposit:bool,deposit_amount:float,balance:float,min_level:array{code:string,name:string}}
     */
    public function requirement(User $user): array
    {
        $amount = $this->fees->quote(PlatformEventFeeService::OWNER_LISTING_DEPOSIT, ['owner' => (int) $user->id], 0)['total'];
        $required = $amount > 0;

        $code = (string) config('bim.owner_listings.min_guarantee_level', 'bronze');
        $target = (string) $user->type === User::TYPE_BUSINESS ? GuaranteeLevel::TARGET_BUSINESS : GuaranteeLevel::TARGET_CLIENT;
        $min = GuaranteeLevel::query()->where('code', $code)->where('target_type', $target)->first();
        $badge = $this->badges->forUsers([(int) $user->id])[(int) $user->id] ?? null;
        $guaranteeOk = $badge !== null && $min !== null && (int) $badge['priority'] >= (int) $min->priority;

        return [
            'required' => $required,
            'guarantee_ok' => $guaranteeOk,
            'needs_deposit' => $required && ! $guaranteeOk,
            'deposit_amount' => $amount,
            'balance' => (float) $this->wallets->getOrCreateWallet((int) $user->id)->balance,
            'min_level' => ['code' => $code, 'name' => (string) ($min?->name_ar ?: $code)],
        ];
    }

    /**
     * Before publishing: fail unless the condition is met — by the guarantee, or by agreeing to the deposit with enough balance.
     * Returns the amount that will be held (0 when the guarantee stands behind the listing or nothing is required).
     */
    public function assertCanPublish(User $user, bool $holdDeposit): float
    {
        $req = $this->requirement($user);

        if (! $req['needs_deposit']) {
            return 0.0;
        }

        $amount = $this->money($req['deposit_amount']);

        if (! $holdDeposit) {
            throw ValidationException::withMessages(['seriousness' => __('لنشر إعلانك: ضمان :level فأعلى، أو عربون جدية :amount جنيه يُحجز من محفظتك ويُردّ عند البيع أو الإنهاء.', ['level' => $req['min_level']['name'], 'amount' => $amount])]);
        }

        if ($req['balance'] < $req['deposit_amount']) {
            throw ValidationException::withMessages(['balance' => __('رصيدك لا يكفي لعربون الجدية (:amount جنيه). اشحن محفظتك أولًا.', ['amount' => $amount])]);
        }

        return (float) $req['deposit_amount'];
    }

    /** Freeze the deposit for this listing (wallet balance → locked), once. */
    public function hold(OwnerListing $listing, float $amount): void
    {
        if ($amount <= 0 || $listing->deposit_state === self::STATE_HELD) {
            return;
        }

        $this->wallets->hold(
            (int) $listing->user_id,
            $amount,
            'owner_listing_deposit',
            (string) $listing->id,
            'عربون جدية إعلان من المالك',
            'ol-deposit-hold:' . $listing->id . ':' . \Illuminate\Support\Str::uuid(),
            ['context' => 'owner_listing_deposit', 'listing_id' => (int) $listing->id]
        );

        $listing->forceFill(['deposit_amount' => $amount, 'deposit_state' => self::STATE_HELD])->save();
    }

    /** Hand the deposit back (locked → balance), once; safe to call when nothing is held. */
    public function release(OwnerListing $listing): void
    {
        if ($listing->deposit_state !== self::STATE_HELD || (float) $listing->deposit_amount <= 0) {
            return;
        }

        $this->wallets->release(
            (int) $listing->user_id,
            (float) $listing->deposit_amount,
            'owner_listing_deposit',
            (string) $listing->id,
            'رد عربون جدية إعلان من المالك',
            'ol-deposit-release:' . $listing->id . ':' . \Illuminate\Support\Str::uuid(),
            ['context' => 'owner_listing_deposit', 'listing_id' => (int) $listing->id]
        );

        $listing->forceFill(['deposit_state' => self::STATE_RELEASED])->save();
    }

    private function money(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
