<?php

namespace App\Services\Notifications;

use App\Events\AppNotificationCreated;
use App\Models\AppNotification;
use App\Models\CommercialOffer;
use App\Models\OfferFollowNotification;

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

    public function createFromOfferFollowNotification(OfferFollowNotification $notification): ?AppNotification
    {
        $notification->loadMissing(['offer.sellerBusiness', 'follow']);

        $offer = $notification->offer;
        if (! $offer) {
            return null;
        }

        $exists = AppNotification::query()
            ->where('user_id', (int) $notification->user_id)
            ->where('source_type', 'offer_follow_notification')
            ->where('source_id', (int) $notification->id)
            ->exists();

        if ($exists) {
            return null;
        }

        $sellerName = optional($offer->sellerBusiness)->name;
        $title = $offer->displayTitle();

        return $this->create([
            'user_id' => (int) $notification->user_id,
            'actor_id' => $offer->seller_business_id ?: null,
            'type' => AppNotification::TYPE_OFFER,
            'priority' => $offer->isBoosted() ? AppNotification::PRIORITY_HIGH : AppNotification::PRIORITY_NORMAL,
            'title_ar' => 'عرض جديد يناسب متابعتك',
            'title_en' => 'New offer matches your follow',
            'body_ar' => trim(($sellerName ? $sellerName . ': ' : '') . $title),
            'body_en' => trim(($sellerName ? $sellerName . ': ' : '') . $title),
            'action_type' => 'open_offer',
            'action_url' => '/offers/' . $offer->id,
            'notifiable_type' => CommercialOffer::class,
            'notifiable_id' => (int) $offer->id,
            'source_type' => 'offer_follow_notification',
            'source_id' => (int) $notification->id,
            'meta' => [
                'match_type' => $notification->match_type,
                'match_score' => (float) $notification->match_score,
                'follow_id' => (int) $notification->follow_id,
                'offer_id' => (int) $offer->id,
                'offerable_type' => $offer->offerable_type,
                'offerable_id' => (int) $offer->offerable_id,
                'audience_type' => $offer->audience_type,
                'seller_business_id' => (int) $offer->seller_business_id,
                'price' => (float) $offer->final_price,
                'currency' => $offer->currency,
                'is_boosted' => $offer->isBoosted(),
            ],
        ]);
    }

    public function syncOfferFollowNotifications(int $limit = 500): int
    {
        $created = 0;

        OfferFollowNotification::query()
            ->with(['offer.sellerBusiness', 'follow'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (OfferFollowNotification $notification) use (&$created) {
                if ($this->createFromOfferFollowNotification($notification)) {
                    $created++;
                }
            });

        return $created;
    }
}
