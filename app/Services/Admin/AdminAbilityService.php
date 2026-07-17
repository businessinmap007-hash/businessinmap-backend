<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * BIM-14.1 — granting and revoking admin abilities.
 *
 * This is the root of the permission system, so the rules live here rather than
 * in the controller: they are the security boundary, and they should be
 * testable without an HTTP request in the way.
 *
 * The rule that makes an ability to grant abilities safe to hand out at all is
 * `grantableBy()`: you can only give away what you already hold. Without it,
 * ROLES would silently be equivalent to every other ability at once — grant
 * yourself MONEY, done. With it, a support lead with DISPUTES + ROLES can staff
 * their own queue and can still never pay anyone out.
 *
 * The wildcard (`*`) is deliberately NOT grantable from the UI. Minting a new
 * root should be hard and deliberate — it takes a migration or tinker.
 */
class AdminAbilityService
{
    /** Admins a human is allowed to see and manage on the roles screen. */
    public function manageableAdmins(): Collection
    {
        $treasuryId = (int) config('bim.platform_wallet_user_id');

        return User::query()
            ->where('type', User::TYPE_ADMIN)
            // The treasury is an account that holds money, not a person who
            // holds powers. It has no login path; listing it would only invite
            // someone to give it some.
            ->when($treasuryId > 0, fn ($q) => $q->where('id', '!=', $treasuryId))
            ->orderBy('id')
            ->get();
    }

    /** Holds Bouncer's wildcard — passes every check, including future ones. */
    public function isSuperAdmin(User $user): bool
    {
        return $user->getAbilities()->contains('name', AdminAbility::WILDCARD);
    }

    /**
     * The named abilities this admin holds. A super-admin is reported as holding
     * all of them, because that is what the wildcard means in practice.
     *
     * @return array<int, string>
     */
    public function abilitiesOf(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return AdminAbility::ALL;
        }

        return array_values(array_intersect(
            AdminAbility::ALL,
            $user->getAbilities()->pluck('name')->all()
        ));
    }

    /**
     * What this actor may hand to someone else — never more than they hold.
     *
     * @return array<int, string>
     */
    public function grantableBy(User $actor): array
    {
        return $this->abilitiesOf($actor);
    }

    /**
     * Why this actor may not touch this target, or null if they may.
     *
     * Returns a reason rather than a bool so the screen can explain itself
     * instead of just greying a row out.
     */
    public function blockReason(User $actor, User $target): ?string
    {
        if ((int) $actor->id === (int) $target->id) {
            // Removes self-lockout and self-escalation in one rule. Nobody needs
            // to edit their own permissions; every reason to want to is a bad one.
            return 'لا يمكنك تعديل صلاحيات حسابك أنت.';
        }

        if ($this->isSuperAdmin($target)) {
            // Blocked for everyone, including another super-admin: sync() refuses
            // wildcards outright, so offering an Edit button here would be an
            // offer the screen cannot keep.
            return $this->isSuperAdmin($actor)
                ? 'مدير عام — صلاحياته لا تُدار من هذه الشاشة.'
                : 'هذا مدير عام — لا يعدّله إلا مدير عام مثله.';
        }

        if ((int) $target->id === (int) config('bim.platform_wallet_user_id')) {
            return 'حساب خزينة المنصة لا يملك صلاحيات لوحة.';
        }

        if ($target->type !== User::TYPE_ADMIN) {
            return 'هذا الحساب ليس مشرفًا.';
        }

        return null;
    }

    public function canManage(User $actor, User $target): bool
    {
        return $this->blockReason($actor, $target) === null;
    }

    /**
     * Set a target's abilities to exactly $wanted.
     *
     * @param array<int, string> $wanted
     * @throws RuntimeException when the actor may not do this
     */
    public function sync(User $actor, User $target, array $wanted): void
    {
        if ($reason = $this->blockReason($actor, $target)) {
            throw new RuntimeException($reason);
        }

        if ($this->isSuperAdmin($target)) {
            // Nothing here may touch a wildcard: stripping the last super-admin
            // would brick the panel, and the UI must not be able to mint or
            // unmake root at all.
            throw new RuntimeException('صلاحيات المدير العام لا تُدار من هذه الشاشة.');
        }

        $wanted = array_values(array_intersect(AdminAbility::ALL, $wanted));
        $current = $this->abilitiesOf($target);
        $grantable = $this->grantableBy($actor);

        // Trying to ADD something the actor does not hold is a real escalation
        // attempt, and is refused loudly — quietly dropping it would tell the
        // caller they succeeded.
        $escalation = array_diff($wanted, $grantable, $current);

        if ($escalation !== []) {
            throw new RuntimeException(
                'لا يمكنك منح صلاحية لا تملكها: ' . implode('، ', array_map(
                    fn ($a) => AdminAbility::label($a),
                    $escalation
                ))
            );
        }

        // Outside the actor's own scope the target keeps exactly what it had.
        // The actor cannot grant those, so they must not be able to revoke them
        // either — least of all by submitting a form whose checkbox was
        // disabled, which posts nothing and would otherwise read as "remove it".
        $untouchable = array_values(array_diff($current, $grantable));

        $effective = array_values(array_unique(array_merge(
            array_values(array_intersect($wanted, $grantable)),
            $untouchable
        )));

        foreach (array_diff($effective, $current) as $grant) {
            Bouncer::allow($target)->to($grant);
        }

        foreach (array_diff($current, $effective) as $revoke) {
            Bouncer::disallow($target)->to($revoke);
        }

        Bouncer::refreshFor($target);

        Log::info('AdminV2 abilities changed.', [
            'actor_id' => (int) $actor->id,
            'target_id' => (int) $target->id,
            'from' => $current,
            'to' => $effective,
        ]);
    }
}
