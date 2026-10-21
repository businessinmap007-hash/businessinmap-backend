<?php

namespace App\Services\Notifications;

use App\Events\AppNotificationCreated;
use App\Models\AppNotification;

final class InAppNotificationService
{
    public function create(array $data): AppNotification
    {
        // A notification is stored once and read later by a recipient whose
        // language we don't know at creation time — so it must hold BOTH
        // languages, not one picked now. Callers pass the Arabic source (and may
        // pass an explicit English); any missing side is filled from the other
        // through the JSON translations. This is why notification content is
        // NOT wrapped in __() at the call site (that would collapse it to the
        // actor's locale) — the raw source string is passed straight through.
        [$titleAr, $titleEn] = $this->bilingual($data['title_ar'] ?? null, $data['title_en'] ?? null);
        [$bodyAr, $bodyEn]   = $this->bilingual($data['body_ar'] ?? null, $data['body_en'] ?? null);

        $notification = AppNotification::query()->create([
            'user_id' => (int) $data['user_id'],
            'actor_id' => $data['actor_id'] ?? null,
            'type' => $data['type'] ?? AppNotification::TYPE_SYSTEM,
            'channel' => $data['channel'] ?? AppNotification::CHANNEL_IN_APP,
            'priority' => $data['priority'] ?? AppNotification::PRIORITY_NORMAL,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'body_ar' => $bodyAr,
            'body_en' => $bodyEn,
            'action_type' => $data['action_type'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'notifiable_type' => $data['notifiable_type'] ?? null,
            'notifiable_id' => $data['notifiable_id'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'status' => $data['status'] ?? AppNotification::STATUS_UNREAD,
            'delivered_at' => $data['delivered_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);

        event(new AppNotificationCreated($notification));

        return $notification;
    }

    /**
     * Fills whichever language is missing from the one that is present. An
     * unmapped string resolves to itself, so a caller that passes only one
     * language always gets it in both slots — never a null the app would
     * render blank. That is the common case for authored content (an order
     * number, a post title) which has no translation and needs none.
     *
     * @return array{0: ?string, 1: ?string} [ar, en]
     */
    private function bilingual(?string $ar, ?string $en): array
    {
        $ar = ($ar === '') ? null : $ar;
        $en = ($en === '') ? null : $en;

        if ($ar !== null && $en === null) {
            $en = trans($ar, [], 'en');
        } elseif ($en !== null && $ar === null) {
            // The lang files are keyed BY the Arabic source string, so going
            // en→ar is a reverse lookup, not a trans() call — trans() would
            // just hand the English back.
            $ar = $this->arabicSourceFor($en) ?? $en;
        }

        return [$ar, $en];
    }

    /** The Arabic key whose English value is $en, if the lang file has one. */
    private function arabicSourceFor(string $en): ?string
    {
        static $flipped = null;

        if ($flipped === null) {
            $flipped = array_flip(array_filter(
                (array) trans()->getLoader()->load('en', '*', '*'),
                'is_string'
            ));
        }

        return $flipped[$en] ?? null;
    }
}
