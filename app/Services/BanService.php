<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BlockedIdentity;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Banning a user, the standalone moderation action.
 *
 * A ban does two things that must stay together: it marks the live account
 * (`banned_at`), and it records the account's identities on the hashed
 * `blocked_identities` list so the ban survives a delete-and-re-register. See
 * [[account-deletion-and-ban]] — the list already existed for the fraud-deletion
 * path; this reuses it rather than inventing new storage.
 *
 * The ban is enforced at login AND on every authenticated API request (the
 * `banned` middleware), and existing tokens are revoked on the spot — otherwise
 * a banned user keeps operating on the token they already hold until it expires.
 */
class BanService
{
    public function __construct(private readonly InAppNotificationService $notifications) {}

    public function ban(User $user, ?string $reason, int $adminId): User
    {
        return DB::transaction(function () use ($user, $reason, $adminId) {
            $user->banned_at = now();
            $user->ban_reason = $reason;
            $user->save();

            // Keyed hash, so a delete-then-re-register with the same email/phone
            // is still caught. Must run while email/phone still exist (they do —
            // this is a live account, not an anonymized one).
            BlockedIdentity::blockUser($user, $reason, BlockedIdentity::SOURCE_MANUAL, $adminId);

            // Kill live sessions: a ban that leaves the current token working is
            // a ban in name only.
            $user->tokens()->delete();

            $this->notify(
                (int) $user->id,
                'تم إيقاف حسابك',
                $reason ? 'أُوقف حسابك: ' . $reason : 'أُوقف حسابك عن الاستخدام.'
            );

            return $user->fresh();
        });
    }

    public function unban(User $user, int $adminId): User
    {
        return DB::transaction(function () use ($user, $adminId) {
            $user->banned_at = null;
            $user->ban_reason = null;
            $user->save();

            // Lift the hashed block for this account's current identities so the
            // person can sign in again. Match by user_id and by hash — a manual
            // ban stores the id, but matching the hash too catches a row added
            // for the same phone under a different account.
            BlockedIdentity::unblockUser($user);

            $this->notify((int) $user->id, 'أُعيد تفعيل حسابك', 'رُفع الإيقاف عن حسابك ويمكنك الدخول من جديد.');

            return $user->fresh();
        });
    }

    private function notify(int $userId, string $titleAr, string $bodyAr): void
    {
        try {
            $this->notifications->create([
                'user_id' => $userId,
                'type' => AppNotification::TYPE_SYSTEM,
                'priority' => AppNotification::PRIORITY_URGENT,
                'title_ar' => $titleAr,
                'body_ar' => $bodyAr,
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
