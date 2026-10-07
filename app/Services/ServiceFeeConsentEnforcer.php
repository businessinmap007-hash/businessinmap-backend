<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserServiceFeeConsent;

/**
 * The single authority that forces a user into the fee + rating programme.
 *
 * Guarantees and deposits are trust instruments. Buying a guarantee (any level)
 * or putting up a deposit must NOT become a way to look trustworthy while
 * dodging service fees — so whenever either happens we force the user's consent
 * on: `fee_auto_charge_enabled` (service fees will be collected) AND
 * `rating_enabled` (the rating opens automatically).
 *
 * Forward-only and idempotent: it only ever ENABLES; it never auto-disables, and
 * calling it repeatedly is a no-op once the user is already in the programme.
 * Admins can still change consent from the panel, but the next guarantee/deposit
 * action re-asserts it.
 */
class ServiceFeeConsentEnforcer
{
    /** Did the last enforce() actually switch something on? (False = the user was already in the programme.) */
    public bool $changedLast = false;

    /** Force the fee + rating consent for a user. Returns the consent row. */
    public function enforce(User $user, string $reason): UserServiceFeeConsent
    {
        $consent = UserServiceFeeConsent::firstOrCreate(
            ['user_id' => (int) $user->id],
            [
                'fee_auto_charge_enabled' => false,
                'rating_enabled' => false,
                'stats_enabled' => false,
            ]
        );

        $changed = false;

        if (! $consent->fee_auto_charge_enabled) {
            $consent->fee_auto_charge_enabled = true;
            $consent->enabled_at = $consent->enabled_at ?: now();
            $consent->disabled_at = null;
            $changed = true;
        }

        if (! $consent->rating_enabled) {
            $consent->rating_enabled = true;
            $changed = true;
        }

        $this->changedLast = $changed;

        if ($changed) {
            $consent->notes = trim((string) $consent->notes . "\n[إلزام تلقائي] " . $reason);
            $consent->save();
        }

        // Keep the users.rating_enabled column in step (hasRatingEnabled reads it).
        if (! (bool) $user->rating_enabled) {
            $user->forceFill(['rating_enabled' => true])->save();
        }

        // «عند استخدام الديبوزت أو الضمان يظهر رسالة فتح التقييم» — said once, when it actually opens.
        if ($changed) {
            $this->announceRatingOpened($user, 'rating_opened:' . $user->id);
        }

        return $consent;
    }

    /**
     * The message that says the rating is open and that platform fees are charged separately from each party's wallet
     * (never from the deposit). `$sourceKey` makes it once per source: the same deposit never says it twice.
     */
    public function announceRatingOpened(User $user, string $sourceKey): void
    {
        try {
            $already = \App\Models\AppNotification::query()
                ->where('user_id', $user->id)
                ->where('source_type', 'wallet_rating_opened')
                ->where('meta->source_key', $sourceKey)
                ->exists();

            if ($already) {
                return;
            }

            app(\App\Services\Notifications\NotificationDispatcherService::class)->dispatch('wallet_rating_opened', (int) $user->id, [
                'title_ar' => 'فُتح التقييم',
                'title_en' => 'Rating is open',
                'body_ar' => 'باستخدامك الديبوزت أو الضمان، فُتح التقييم لحسابك. رسوم المنصة تُخصم من محفظتك بشكل منفصل، ولا تُحسب من الديبوزت.',
                'body_en' => 'By using a deposit or a guarantee, rating is now open on your account. Platform fees are charged separately from your wallet, never from the deposit.',
                'meta' => ['source_key' => $sourceKey],
                'skip_realtime' => true,
            ]);
        } catch (\Throwable $e) {
            report($e); // a missed notice must never undo a deposit or a guarantee
        }
    }

    /** Convenience: enforce by user id when the model isn't already loaded. */
    public function enforceById(int $userId, string $reason): ?UserServiceFeeConsent
    {
        if ($userId <= 0) {
            return null;
        }

        $user = User::find($userId);

        return $user ? $this->enforce($user, $reason) : null;
    }
}
