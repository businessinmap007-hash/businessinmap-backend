<?php

namespace App\Services\Guarantees;

use App\Models\TrustedPartner;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * A business's standing waiver for a specific, already-known user — "we've
 * dealt before, I vouch for him" (the owner's own framing, 2026-08-28) —
 * so THAT user's orders from THAT business skip the deposit_required_above
 * check (CustomerCartService::assessDeposit) from now on, until revoked.
 *
 * Deliberately NOT money-backed, unlike a friend co-guarantor: nothing is
 * frozen on the voucher's side, and a failed operation is the two parties'
 * own problem to sort out ("يتعامل معه كأنه مسجل لديه في فريق التوصيل" —
 * the owner's own words) — the platform is not on the hook either way.
 *
 * The one hard precondition: the vouched user must currently hold SOME
 * active guarantee of their own — not a total stranger with zero standing.
 * Checked live at use time (isWaived), not just once at vouch time, so a
 * guarantee that later lapses quietly stops honouring the waiver rather
 * than leaving a stale, unconditional pass.
 */
class TrustedPartnerService
{
    public function __construct(private readonly GuaranteeCoverageService $coverage)
    {
    }

    /**
     * Vouch for an EXISTING user by phone — same find-never-mint pattern as
     * business_staff/delivery_drivers, never a new signup flow.
     */
    public function vouch(int $businessId, string $lookupPhone): TrustedPartner
    {
        $user = User::query()->where('phone', trim($lookupPhone))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => __('لم يُعثر على مستخدم بهذا الهاتف.'),
            ]);
        }

        if ((int) $user->id === $businessId) {
            throw ValidationException::withMessages([
                'phone' => __('لا يمكنك توثيق نفسك.'),
            ]);
        }

        if (! $this->coverage->activeGuarantee($user)) {
            throw ValidationException::withMessages([
                'phone' => __('لا يملك هذا المستخدم ضمانًا فعّالاً — لا يمكن توثيقه قبل أن يكون لديه مستوى ضمان.'),
            ]);
        }

        return TrustedPartner::query()->updateOrCreate(
            ['business_id' => $businessId, 'user_id' => $user->id],
            ['is_active' => true],
        );
    }

    public function setActive(int $businessId, int $partnerId, bool $active): TrustedPartner
    {
        $row = TrustedPartner::query()->where('id', $partnerId)->where('business_id', $businessId)->first();

        if (! $row) {
            abort(404, __('هذا الشريك الموثّق لا يخص نشاطك.'));
        }

        $row->update(['is_active' => $active]);

        return $row;
    }

    /**
     * Is this business's deposit requirement waived for this user RIGHT NOW —
     * both a standing, active vouch AND a currently-held guarantee, checked
     * together every time, never cached from vouch time.
     */
    public function isWaived(int $businessId, int $userId): bool
    {
        $vouched = TrustedPartner::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        if (! $vouched) {
            return false;
        }

        $user = User::query()->find($userId);

        return $user && $this->coverage->activeGuarantee($user) !== null;
    }

    /** @return \Illuminate\Support\Collection<int,TrustedPartner> */
    public function roster(int $businessId)
    {
        return TrustedPartner::query()
            ->where('business_id', $businessId)
            ->with('user:id,name,phone')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
    }
}
